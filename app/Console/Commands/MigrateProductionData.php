<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateProductionData extends Command
{
    protected $signature = 'db:migrate-production 
                            {--source=old_local : Source connection (mysql_production or mysql_old_local)}
                            {--target=mysql : Target connection (default: mysql)}
                            {--step= : Run a specific step (users,roles,customers,branches,budgets,technicians,payments,media)}
                            {--dry-run : Show what would be migrated without inserting}';

    protected $description = 'Migrate data from old production schema to the new ERP schema';

    private string $source;
    private string $target;
    private bool $dryRun;
    private array $stats = [];
    private array $branchCache = [];

    public function handle(): int
    {
        $this->source = $this->option('source');
        $this->target = $this->option('target');
        $this->dryRun = $this->option('dry-run');

        $this->info("Source: {$this->source}  →  Target: {$this->target}");
        if ($this->dryRun) {
            $this->warn('DRY RUN — no data will be inserted');
        }

        // Verify source connection
        try {
            DB::connection($this->source)->getPdo();
            $this->info("✓ Connected to source: {$this->source}");
        } catch (\Exception $e) {
            $this->error("Cannot connect to source: {$this->source}");
            $this->error($e->getMessage());
            return 1;
        }

        // Verify target connection
        try {
            DB::connection($this->target)->getPdo();
            $this->info("✓ Connected to target: {$this->target}");
        } catch (\Exception $e) {
            $this->error("Cannot connect to target: {$this->target}");
            return 1;
        }

        $step = $this->option('step');

        // IMPORTANT: Order matters due to foreign keys
        $steps = [
            'users'       => fn() => $this->migrateUsers(),
            'roles'       => fn() => $this->migrateRoles(),
            'customers'   => fn() => $this->migrateCustomers(),
            'branches'    => fn() => $this->migrateBranchesAndContacts(),
            'technicians' => fn() => $this->migrateTechnicians(),
            'budgets'     => fn() => $this->migrateBudgetsAndTickets(),
            'payments'    => fn() => $this->migratePayments(),
            'media'       => fn() => $this->migrateMedia(),
        ];

        if ($step) {
            if (!isset($steps[$step])) {
                $this->error("Unknown step: {$step}. Available: " . implode(', ', array_keys($steps)));
                return 1;
            }
            $steps[$step]();
        } else {
            $progress = $this->output->createProgressBar(count($steps));
            $progress->setFormat(' %current%/%max% [%bar%] %message%');

            foreach ($steps as $name => $callback) {
                $progress->setMessage($name);
                $callback();
                $progress->advance();
            }
            $progress->finish();
            $this->newLine(2);
        }

        $this->showSummary();

        return 0;
    }

    // ──────────────────────────────────────────────
    //  STEP 1: Users
    // ──────────────────────────────────────────────
    private function migrateUsers(): void
    {
        $this->info('Migrating users...');

        $existingEmails = DB::connection($this->target)
            ->table('users')->pluck('email')->toArray();

        $users = DB::connection($this->source)
            ->table('users')
            ->whereNotIn('email', $existingEmails)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            if ($this->dryRun) {
                $count++;
                continue;
            }
            DB::connection($this->target)->table('users')->insert((array) $user);
            $count++;
        }

        // Also migrate employees
        $existingEmpUserIds = DB::connection($this->target)
            ->table('employees')->pluck('user_id')->toArray();

        $employees = DB::connection($this->source)
            ->table('employees')
            ->whereNotIn('user_id', $existingEmpUserIds)
            ->get();

        $empCount = 0;
        foreach ($employees as $emp) {
            if ($this->dryRun) {
                $empCount++;
                continue;
            }
            DB::connection($this->target)->table('employees')->insert((array) $emp);
            $empCount++;
        }

        $this->stats['users'] = $count;
        $this->stats['employees'] = $empCount;
        $this->info("  Users: {$count}, Employees: {$empCount}");
    }

    // ──────────────────────────────────────────────
    //  STEP 2: Roles & Permissions (Spatie)
    // ──────────────────────────────────────────────
    private function migrateRoles(): void
    {
        $this->info('Migrating roles & permissions...');

        $tables = ['permissions', 'roles', 'model_has_roles', 'role_has_permissions'];

        foreach ($tables as $table) {
            // Skip if target already has data
            $existingCount = DB::connection($this->target)->table($table)->count();
            if ($existingCount > 0) {
                $this->warn("  {$table}: already has {$existingCount} records, skipping");
                continue;
            }

            $rows = DB::connection($this->source)->table($table)->get();
            $count = 0;

            foreach ($rows as $row) {
                if ($this->dryRun) {
                    $count++;
                    continue;
                }
                DB::connection($this->target)->table($table)->insert((array) $row);
                $count++;
            }

            $this->stats[$table] = $count;
        }
    }

    // ──────────────────────────────────────────────
    //  STEP 3: Customers
    // ──────────────────────────────────────────────
    private function migrateCustomers(): void
    {
        $this->info('Migrating customers...');

        $existingIds = DB::connection($this->target)
            ->table('customers')->pluck('id')->toArray();

        $customers = DB::connection($this->source)
            ->table('customers')
            ->whereNotIn('id', $existingIds)
            ->get();

        $count = 0;
        foreach ($customers as $customer) {
            if ($this->dryRun) {
                $count++;
                continue;
            }
            DB::connection($this->target)->table('customers')->insert((array) $customer);
            $count++;
        }

        $this->stats['customers'] = $count;
        $this->info("  Customers: {$count}");
    }

    // ──────────────────────────────────────────────
    //  STEP 4: Branches & Contacts (major restructure)
    // ──────────────────────────────────────────────
    private function migrateBranchesAndContacts(): void
    {
        $this->info('Migrating customer branches & contacts...');

        $oldContacts = DB::connection($this->source)
            ->table('customer_contacts')
            ->get();

        $branchCount = 0;
        $contactCount = 0;
        $pivotCount = 0;

        foreach ($oldContacts as $oldContact) {
            if ($this->dryRun) {
                // Count what would be created
                $branchNames = $this->parseBranchNames($oldContact->branches ?? '');
                $branchCount += count($branchNames);
                $contactCount++;
                $pivotCount += count($branchNames);
                continue;
            }

            // 1. Insert the contact (preserving original ID for FK integrity)
            $contactData = [
                'id'          => $oldContact->id,
                'customer_id' => $oldContact->customer_id,
                'name'        => $oldContact->name,
                'email'       => $oldContact->email,
                'phone'       => $oldContact->phone,
                'position'    => $oldContact->position,
                'created_at'  => $oldContact->created_at,
                'updated_at'  => $oldContact->updated_at,
            ];

            // Check if contact already exists by original ID
            $existing = DB::connection($this->target)
                ->table('customer_contacts')
                ->where('id', $oldContact->id)
                ->first();

            if ($existing) {
                $newContactId = $existing->id;
            } else {
                try {
                    $newContactId = DB::connection($this->target)
                        ->table('customer_contacts')
                        ->insertGetId($contactData);
                } catch (\Exception $e) {
                    $this->warn("  Could not insert contact #{$oldContact->id}: " . $e->getMessage());
                    continue;
                }
            }
            $contactCount++;

            // 2. Parse branches from the old text field and create customer_branches
            $branchNames = $this->parseBranchNames($oldContact->branches ?? '');

            foreach ($branchNames as $branchName) {
                $branchId = $this->findOrCreateBranch(
                    $oldContact->customer_id,
                    $branchName
                );

                if (!$branchId) {
                    continue;
                }
                $branchCount++;

                // 3. Create pivot record
                try {
                    $pivotExists = DB::connection($this->target)
                        ->table('customer_branch_contact')
                        ->where('customer_branch_id', $branchId)
                        ->where('customer_contact_id', $newContactId)
                        ->exists();

                    if (!$pivotExists) {
                        DB::connection($this->target)
                            ->table('customer_branch_contact')
                            ->insert([
                                'customer_branch_id' => $branchId,
                                'customer_contact_id' => $newContactId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        $pivotCount++;
                    }
                } catch (\Exception $e) {
                    $this->warn("  Pivot error branch#{$branchId} contact#{$newContactId}: " . $e->getMessage());
                }
            }
        }

        $this->stats['customer_contacts'] = $contactCount;
        $this->stats['customer_branches'] = $branchCount;
        $this->stats['customer_branch_contact'] = $pivotCount;
        $this->info("  Contacts: {$contactCount}, Branches: {$branchCount}, Pivots: {$pivotCount}");
    }

    private function parseBranchNames(?string $branchesText): array
    {
        if (empty($branchesText)) {
            return [];
        }

        // Split by comma, trim whitespace and commas, filter empty
        $names = array_filter(
            array_map('trim', explode(',', $branchesText)),
            fn($n) => !empty($n)
        );

        return array_values($names);
    }

    private function findOrCreateBranch(int $customerId, string $branchName): int
    {
        $cacheKey = "{$customerId}::{$branchName}";

        if (isset($this->branchCache[$cacheKey])) {
            return $this->branchCache[$cacheKey];
        }

        // Try to find existing branch
        $branch = DB::connection($this->target)
            ->table('customer_branches')
            ->where('customer_id', $customerId)
            ->where('branch_name', $branchName)
            ->first();

        if ($branch) {
            $this->branchCache[$cacheKey] = $branch->id;
            return $branch->id;
        }

        // Create new branch
        try {
            $id = DB::connection($this->target)
                ->table('customer_branches')
                ->insertGetId([
                    'customer_id' => $customerId,
                    'country'     => 'México',
                    'region'      => $this->guessRegion($branchName),
                    'unit'        => $branchName,
                    'branch_name' => $branchName,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
        } catch (\Exception $e) {
            $this->warn("  Could not create branch '{$branchName}': " . $e->getMessage());
            return 0;
        }

        $this->branchCache[$cacheKey] = $id;
        return $id;
    }

    private function guessRegion(string $branchName): string
    {
        $cities = [
            'Guadalajara', 'Gdl', 'Zapopan', 'Tlaquepaque', 'Tonalá',
            'Puerto Vallarta', 'Vallarta', 'Pitillal',
            'Celaya', 'León', 'Morelia', 'Zamora',
            'Acapulco', 'Chilpancingo', 'Cuernavaca',
            'Mérida', 'Campeche', 'Chetumal',
            'Tapachula', 'Tuxtla', 'Xalapa',
            'Reynosa', 'Matamoros', 'Tijuana', 'Ensenada',
            'Hermosillo', 'Culiacán', 'Mazatlán', 'Los Cabos', 'La Paz',
            'Querétaro', 'San Luis Potosí', 'Tepic',
            'Ciudad Juárez', 'Metepec', 'Tecoman', 'Colima',
            'Tabasco', 'Veracruz',
        ];

        $upperName = mb_strtoupper(trim($branchName));

        foreach ($cities as $city) {
            if (mb_strpos($upperName, mb_strtoupper($city)) !== false) {
                return $city;
            }
        }

        // Fallback: use the branch name itself
        return $branchName;
    }

    // ──────────────────────────────────────────────
    //  STEP 5: Technicians
    // ──────────────────────────────────────────────
    private function migrateTechnicians(): void
    {
        $this->info('Migrating technicians...');

        $existingUserIds = DB::connection($this->target)
            ->table('technicians')->pluck('user_id')->toArray();

        $technicians = DB::connection($this->source)
            ->table('technicians')
            ->whereNotIn('user_id', $existingUserIds)
            ->get();

        $count = 0;
        foreach ($technicians as $tech) {
            if ($this->dryRun) {
                $count++;
                continue;
            }

            $data = (array) $tech;

            // Ensure specialties is valid JSON
            if (isset($data['specialties']) && is_string($data['specialties'])) {
                $decoded = json_decode($data['specialties'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $data['specialties'] = json_encode([]);
                }
            }

            DB::connection($this->target)->table('technicians')->insert($data);
            $count++;
        }

        $this->stats['technicians'] = $count;
        $this->info("  Technicians: {$count}");
    }

    // ──────────────────────────────────────────────
    //  STEP 6: Budgets → Tickets + Budgets (MAJOR)
    // ──────────────────────────────────────────────
    private function migrateBudgetsAndTickets(): void
    {
        $this->info('Migrating budgets → tickets + budgets...');

        // First, build a lookup of old tickets keyed by budget_id
        $oldTickets = DB::connection($this->source)
            ->table('tickets')
            ->get()
            ->keyBy('budget_id');

        $oldBudgets = DB::connection($this->source)
            ->table('budgets')
            ->orderBy('id')
            ->get();

        $ticketCount = 0;
        $budgetCount = 0;
        $conceptCount = 0;
        $taskCount = 0;

        // Build branch lookup from names to IDs
        $branches = DB::connection($this->target)
            ->table('customer_branches')
            ->get()
            ->keyBy('branch_name');

        foreach ($oldBudgets as $oldBudget) {
            $oldTicket = $oldTickets->get($oldBudget->id);

            // Map old budget status to new ticket status
            $ticketStatus = $this->mapBudgetStatusToTicketStatus($oldBudget->status);

            // Find customer_branch_id from old branch name
            $customerBranchId = null;
            if (!empty($oldBudget->branch)) {
                $branchName = trim($oldBudget->branch);
                // Try exact match first
                $branch = $branches->first(fn($b) => 
                    mb_strtoupper($b->branch_name) === mb_strtoupper($branchName)
                );
                if ($branch) {
                    $customerBranchId = $branch->id;
                }
            }

            if ($this->dryRun) {
                $ticketCount++;
                $budgetCount++;
                // Count concepts and tasks
                $conceptCount += DB::connection($this->source)
                    ->table('budget_concepts')
                    ->where('budget_id', $oldBudget->id)
                    ->count();
                if ($oldTicket) {
                    $taskCount += DB::connection($this->source)
                        ->table('ticket_tasks')
                        ->where('ticket_id', $oldTicket->id)
                        ->count();
                }
                continue;
            }

            // --- 1. Create the new TICKET ---
            $ticketId = DB::connection($this->target)->table('tickets')->insertGetId([
                'customer_id'         => $oldBudget->customer_id,
                'customer_contact_id' => $oldBudget->customer_contact_id,
                'customer_branch_id'  => $customerBranchId,
                'seller_id'           => $oldBudget->user_id,
                'name'                => $oldBudget->name,
                'service_type'        => $oldBudget->service_type,
                'duration'            => $oldBudget->duration,
                'technicians'         => null, // Will be populated from old ticket tasks
                'status'              => $ticketStatus,
                'priority'            => $oldBudget->priority ?? 'Media',
                'scheduled_start'     => $oldTicket->scheduled_start ?? null,
                'scheduled_end'       => $oldTicket->scheduled_end ?? null,
                'instructions'        => $oldTicket->instructions ?? null,
                'created_at'          => $oldBudget->created_at,
                'updated_at'          => $oldBudget->updated_at ?? $oldBudget->created_at,
            ]);
            $ticketCount++;

            // --- 2. Create the new BUDGET linked to the ticket ---
            $budgetStatus = $this->mapBudgetStatusToNewBudgetStatus($oldBudget->status);

            DB::connection($this->target)->table('budgets')->insert([
                'ticket_id'     => $ticketId,
                'status'        => $budgetStatus,
                'description'   => $oldBudget->description,
                'currency'      => $oldBudget->currency ?? 'MXN',
                'exchange_rate' => $oldBudget->exchange_rate ?? 1.0000,
                'user_id'       => $oldBudget->user_id,
                'invoice_date'  => null,
                'invoice_number'=> null,
                'created_at'    => $oldBudget->created_at,
                'updated_at'    => $oldBudget->updated_at ?? $oldBudget->created_at,
            ]);
            $budgetCount++;

            // Store mapping for later steps (payments, media, technician_payments)
            if (!isset($this->stats['_budget_id_map'])) {
                $this->stats['_budget_id_map'] = [];
            }
            $this->stats['_budget_id_map'][$oldBudget->id] = $ticketId;

            // Store old ticket ID → new ticket ID mapping for task migration
            if ($oldTicket && !isset($this->stats['_ticket_id_map'])) {
                $this->stats['_ticket_id_map'] = [];
            }
            if ($oldTicket) {
                $this->stats['_ticket_id_map'][$oldTicket->id] = $ticketId;
            }

            // --- 3. Migrate budget concepts (same structure) ---
            $concepts = DB::connection($this->source)
                ->table('budget_concepts')
                ->where('budget_id', $oldBudget->id)
                ->get();

            foreach ($concepts as $concept) {
                $conceptData = (array) $concept;
                unset($conceptData['id']); // Let auto-increment handle it
                $conceptData['budget_id'] = $budgetId ?? DB::connection($this->target)
                    ->table('budgets')
                    ->where('ticket_id', $ticketId)
                    ->value('id');

                DB::connection($this->target)->table('budget_concepts')->insert($conceptData);
                $conceptCount++;
            }

            // --- 4. Migrate old ticket tasks if they existed ---
            if ($oldTicket) {
                $tasks = DB::connection($this->source)
                    ->table('ticket_tasks')
                    ->where('ticket_id', $oldTicket->id)
                    ->get();

                $technicianIds = [];
                foreach ($tasks as $task) {
                    $taskData = (array) $task;
                    unset($taskData['id']);
                    $taskData['ticket_id'] = $ticketId;
                    DB::connection($this->target)->table('ticket_tasks')->insert($taskData);
                    $taskCount++;

                    if ($task->user_id) {
                        $technicianIds[] = $task->user_id;
                    }
                }

                // Update ticket with technician IDs
                if (!empty($technicianIds)) {
                    DB::connection($this->target)
                        ->table('tickets')
                        ->where('id', $ticketId)
                        ->update(['technicians' => json_encode(array_unique($technicianIds))]);
                }
            }
        }

        $this->stats['tickets'] = $ticketCount;
        $this->stats['budgets'] = $budgetCount;
        $this->stats['budget_concepts'] = $conceptCount;
        $this->stats['ticket_tasks'] = $taskCount;
        $this->info("  Tickets: {$ticketCount}, Budgets: {$budgetCount}");
    }

    private function mapBudgetStatusToTicketStatus(string $oldStatus): string
    {
        return match ($oldStatus) {
            'Presupuesto enviado' => 'Levantamiento',
            'Trabajo en proceso'  => 'Proceso de ejecución',
            'Presupuesto aceptado'=> 'Catálogo',
            'Facturado'           => 'Facturado',
            'Pagado'              => 'Pagado',
            'Rechazado'           => 'Borrador',
            default               => 'Borrador',
        };
    }

    private function mapBudgetStatusToNewBudgetStatus(string $oldStatus): string
    {
        return match ($oldStatus) {
            'Presupuesto enviado' => 'Enviado al cliente',
            'Trabajo en proceso'  => 'Aprobado',
            'Presupuesto aceptado'=> 'Aprobado',
            'Facturado'           => 'Aprobado',
            'Pagado'              => 'Aprobado',
            'Rechazado'           => 'Rechazado',
            default               => 'Borrador',
        };
    }

    // ──────────────────────────────────────────────
    //  STEP 7: Payments
    // ──────────────────────────────────────────────
    private function migratePayments(): void
    {
        $this->info('Migrating payments...');

        $budgetIdMap = $this->stats['_budget_id_map'] ?? [];
        if (empty($budgetIdMap)) {
            $this->warn('  No budget ID map found. Run budgets step first.');
            return;
        }

        // We need old_budget_id → new_budget_id mapping
        $newBudgetIds = [];
        foreach ($budgetIdMap as $oldBudgetId => $newTicketId) {
            $newBudgetId = DB::connection($this->target)
                ->table('budgets')
                ->where('ticket_id', $newTicketId)
                ->value('id');
            if ($newBudgetId) {
                $newBudgetIds[$oldBudgetId] = $newBudgetId;
            }
        }

        // --- Budget payments ---
        $oldPayments = DB::connection($this->source)
            ->table('budget_payments')
            ->get();

        $payCount = 0;
        foreach ($oldPayments as $payment) {
            $newBudgetId = $newBudgetIds[$payment->budget_id] ?? null;
            if (!$newBudgetId) {
                $this->warn("  Skipping budget_payment #{$payment->id}: budget #{$payment->budget_id} not migrated");
                continue;
            }

            if ($this->dryRun) {
                $payCount++;
                continue;
            }

            $data = (array) $payment;
            unset($data['id']);
            $data['budget_id'] = $newBudgetId;
            DB::connection($this->target)->table('budget_payments')->insert($data);
            $payCount++;
        }

        // --- Technician payments ---
        $oldTechPayments = DB::connection($this->source)
            ->table('technician_payments')
            ->get();

        $techPayCount = 0;
        foreach ($oldTechPayments as $payment) {
            $newBudgetId = $newBudgetIds[$payment->budget_id] ?? null;
            if (!$newBudgetId) {
                $this->warn("  Skipping technician_payment #{$payment->id}: budget #{$payment->budget_id} not migrated");
                continue;
            }

            if ($this->dryRun) {
                $techPayCount++;
                continue;
            }

            $data = (array) $payment;
            unset($data['id']);
            $data['budget_id'] = $newBudgetId;
            DB::connection($this->target)->table('technician_payments')->insert($data);
            $techPayCount++;
        }

        $this->stats['budget_payments'] = $payCount;
        $this->stats['technician_payments'] = $techPayCount;
        $this->info("  Budget payments: {$payCount}, Technician payments: {$techPayCount}");
    }

    // ──────────────────────────────────────────────
    //  STEP 8: Media (Spatie Media Library)
    // ──────────────────────────────────────────────
    private function migrateMedia(): void
    {
        $this->info('Migrating media...');

        $existingIds = DB::connection($this->target)
            ->table('media')->pluck('id')->toArray();

        $media = DB::connection($this->source)
            ->table('media')
            ->whereNotIn('id', $existingIds)
            ->get();

        $count = 0;
        $budgetIdMap = $this->stats['_budget_id_map'] ?? [];
        $newBudgetIds = [];

        foreach ($budgetIdMap as $oldBudgetId => $newTicketId) {
            $newBudgetId = DB::connection($this->target)
                ->table('budgets')
                ->where('ticket_id', $newTicketId)
                ->value('id');
            if ($newBudgetId) {
                $newBudgetIds[$oldBudgetId] = $newBudgetId;
            }
        }

        foreach ($media as $item) {
            // Update model_id for media attached to budgets
            $data = (array) $item;
            unset($data['id']);

            if ($item->model_type === 'App\\Models\\BudgetPayment') {
                // BudgetPayment model_id points to budget_payments.budget_id
                if (isset($newBudgetIds[$item->model_id])) {
                    $data['model_id'] = $newBudgetIds[$item->model_id];
                }
            }

            if ($this->dryRun) {
                $count++;
                continue;
            }

            DB::connection($this->target)->table('media')->insert($data);
            $count++;
        }

        // Also copy calendars
        $calendars = DB::connection($this->source)
            ->table('calendars')
            ->whereNotIn('id', DB::connection($this->target)->table('calendars')->pluck('id')->toArray())
            ->get();

        $calCount = 0;
        foreach ($calendars as $cal) {
            if ($this->dryRun) {
                $calCount++;
                continue;
            }
            DB::connection($this->target)->table('calendars')->insert((array) $cal);
            $calCount++;
        }

        $this->stats['media'] = $count;
        $this->stats['calendars'] = $calCount;
        $this->info("  Media: {$count}, Calendars: {$calCount}");
    }

    // ──────────────────────────────────────────────
    //  Summary
    // ──────────────────────────────────────────────
    private function showSummary(): void
    {
        $this->newLine();
        $this->line(str_repeat('─', 50));
        $this->info('  MIGRATION SUMMARY');
        $this->line(str_repeat('─', 50));

        $displayStats = array_filter($this->stats, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);

        foreach ($displayStats as $table => $count) {
            $this->line("  <info>{$table}</info>: {$count}");
        }

        $this->line(str_repeat('─', 50));

        if ($this->dryRun) {
            $this->warn('  DRY RUN — no data was actually inserted');
            $this->line('  Run without --dry-run to execute the migration');
        }
    }
}
