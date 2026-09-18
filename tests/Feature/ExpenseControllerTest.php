<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetConcept;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Expenses\BudgetExpenseService;
use App\Services\Expenses\ExpenseService;
use App\Services\Export\XlsxWriterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'expenses.index', 'guard_name' => 'web', 'category' => 'Control de gastos', 'description' => 'View expenses']);
        Permission::create(['name' => 'expenses.create', 'guard_name' => 'web', 'category' => 'Control de gastos', 'description' => 'Create expenses']);
        Permission::create(['name' => 'expenses.edit', 'guard_name' => 'web', 'category' => 'Control de gastos', 'description' => 'Edit expenses']);
        Permission::create(['name' => 'expenses.delete', 'guard_name' => 'web', 'category' => 'Control de gastos', 'description' => 'Delete expenses']);
        Permission::create(['name' => 'expenses.categories.manage', 'guard_name' => 'web', 'category' => 'Control de gastos', 'description' => 'Manage expense categories']);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->givePermissionTo([
            'expenses.index',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            'expenses.categories.manage',
        ]);
    }

    // --- index ---

    public function test_index_renders_expenses_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Index')
                ->has('expenses')
                ->has('categories')
                ->has('stats')
                ->has('filters')
            );
    }

    public function test_index_forbidden_without_permission(): void
    {
        $noPermissionUser = User::factory()->create(['is_active' => true]);

        $this->actingAs($noPermissionUser)
            ->get(route('expenses.index'))
            ->assertForbidden();
    }

    public function test_index_lists_expenses_with_summary_totals(): void
    {
        $budget = Budget::factory()->create();

        Expense::factory()->pending()->create(['amount' => 1000, 'concept' => 'Renta de oficina septiembre']);
        Expense::factory()->paid()->create(['amount' => 2500.50, 'concept' => 'Servicio de internet']);
        Expense::factory()->pending()->create([
            'amount' => 500,
            'concept' => 'Insumos de obra',
            'budget_id' => $budget->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Index')
                ->has('expenses.data', 3)
                ->where('stats.total_count', 3)
                ->where('stats.total_amount', 4000.5)
                ->where('stats.pending_count', 2)
                ->where('stats.pending_amount', 1500)
                ->where('stats.paid_count', 1)
                ->where('stats.paid_amount', 2500.5)
                ->where('stats.budget_count', 1)
                ->where('stats.budget_amount', 500)
            );
    }

    public function test_index_filters_expenses_by_status_and_keeps_summary_totals(): void
    {
        $pending = Expense::factory()->pending()->create(['concept' => 'Gasolina ruta norte']);
        Expense::factory()->paid()->create(['concept' => 'Gasolina ruta sur']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $pending->id)
                ->where('stats.total_count', 2)
            );
    }

    public function test_index_filters_expenses_by_search_term(): void
    {
        Expense::factory()->create(['concept' => 'Gasolina ruta norte', 'reference' => null]);
        $paper = Expense::factory()->create(['concept' => 'Hojas blancas', 'reference' => null]);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['search' => 'hojas']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $paper->id)
            );
    }

    public function test_index_filters_expenses_by_category(): void
    {
        $transport = ExpenseCategory::factory()->create(['name' => 'Transporte']);
        $office = ExpenseCategory::factory()->create(['name' => 'Papelería y consumibles']);

        Expense::factory()->create(['expense_category_id' => $transport->id]);
        Expense::factory()->create(['expense_category_id' => $transport->id]);
        Expense::factory()->create(['expense_category_id' => $office->id]);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['category_id' => $transport->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 2)
            );
    }

    public function test_index_filters_expenses_by_date_range(): void
    {
        $inside = Expense::factory()->create(['expense_date' => '2026-09-10']);
        Expense::factory()->create(['expense_date' => '2026-08-15']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $inside->id)
            );
    }

    // --- folio generation ---

    public function test_expenses_receive_an_autogenerated_folio(): void
    {
        $first = Expense::factory()->create();
        $second = Expense::factory()->create();

        $this->assertSame('GAS-0001', $first->folio);
        $this->assertSame('GAS-0002', $second->folio);
    }

    // --- store ---

    public function test_store_creates_a_general_expense(): void
    {
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'concept' => 'Renta de oficina de octubre',
                'amount' => 4500.75,
                'expense_date' => '2026-09-15',
                'status' => 'pending',
                'payment_method' => 'transfer',
                'reference' => 'FAC-9911',
                'notes' => 'Pago mensual',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'concept' => 'Renta de oficina de octubre',
            'ticket_id' => null,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_creates_a_budget_linked_expense_without_category(): void
    {
        $budget = Budget::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.store'), [
                'budget_id' => $budget->id,
                'concept' => 'Compra de material para el presupuesto',
                'amount' => 1200,
                'expense_date' => '2026-09-15',
                'status' => 'paid',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $expense = Expense::where('concept', 'Compra de material para el presupuesto')->firstOrFail();

        $this->assertSame($budget->id, $expense->budget_id);
        $this->assertNull($expense->expense_category_id);
        $this->assertSame('paid', $expense->status);
        $this->assertNotNull($expense->paid_at);
    }

    public function test_store_creates_a_commission_linked_to_a_budget(): void
    {
        $budget = Budget::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.store'), [
                'budget_id' => $budget->id,
                'is_commission' => true,
                'concept' => 'Comisión de venta',
                'amount' => 900,
                'expense_date' => '2026-09-15',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $expense = Expense::where('concept', 'Comisión de venta')->firstOrFail();

        $this->assertSame($budget->id, $expense->budget_id);
        $this->assertTrue($expense->is_commission);
    }

    public function test_store_requires_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('expenses.store'), [])
            ->assertSessionHasErrors(['expense_category_id', 'concept', 'amount', 'expense_date', 'status']);
    }

    public function test_store_is_forbidden_without_create_permission(): void
    {
        $category = ExpenseCategory::factory()->create();
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('expenses.index');

        $this->actingAs($viewer)
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'concept' => 'Gasto sin permiso',
                'amount' => 100,
                'expense_date' => '2026-09-15',
                'status' => 'pending',
            ])
            ->assertForbidden();
    }

    // --- update ---

    public function test_update_modifies_the_expense_and_stamps_paid_at(): void
    {
        $expense = Expense::factory()->pending()->create(['amount' => 800]);
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => 'Concepto actualizado',
                'amount' => 950.50,
                'expense_date' => '2026-09-10',
                'status' => 'paid',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $expense->refresh();

        $this->assertSame('Concepto actualizado', $expense->concept);
        $this->assertSame('950.50', $expense->amount);
        $this->assertSame('paid', $expense->status);
        $this->assertNotNull($expense->paid_at);
    }

    public function test_update_clears_paid_at_when_the_expense_is_no_longer_paid(): void
    {
        $expense = Expense::factory()->paid()->create();
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => $expense->concept,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'status' => 'pending',
            ])
            ->assertRedirect();

        $this->assertNull($expense->fresh()->paid_at);
    }

    // --- mark paid ---

    public function test_mark_paid_updates_status_and_timestamp(): void
    {
        $expense = Expense::factory()->pending()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.mark-paid', $expense))
            ->assertRedirect()
            ->assertSessionHas('success');

        $expense->refresh();

        $this->assertSame('paid', $expense->status);
        $this->assertNotNull($expense->paid_at);
    }

    // --- destroy ---

    public function test_destroy_deletes_the_expense(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    // --- budget search ---

    public function test_budget_search_returns_matching_budgets_with_their_status(): void
    {
        $ticket = Ticket::factory()->create(['name' => 'Instalación eléctrica nave 3', 'status' => 'Levantamiento']);
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        Budget::factory()->create();

        $this->actingAs($this->user)
            ->getJson(route('expenses.budgets.search', ['q' => 'Instalación eléctrica']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $budget->id,
                'name' => 'Instalación eléctrica nave 3',
                'status' => 'Levantamiento',
            ]);
    }

    public function test_budget_search_filters_by_ticket_status(): void
    {
        $inExecution = Budget::factory()->create([
            'ticket_id' => Ticket::factory()->create(['status' => 'Proceso de ejecución'])->id,
        ]);
        Budget::factory()->create([
            'ticket_id' => Ticket::factory()->create(['status' => 'Levantamiento'])->id,
        ]);

        $this->actingAs($this->user)
            ->getJson(route('expenses.budgets.search', ['status' => 'Proceso de ejecución']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $inExecution->id]);
    }

    public function test_budget_search_requires_expense_write_permission(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('expenses.index');

        $this->actingAs($viewer)
            ->getJson(route('expenses.budgets.search'))
            ->assertForbidden();
    }

    // --- categories ---

    public function test_categories_index_requires_manage_permission(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('expenses.index');

        $this->actingAs($viewer)
            ->getJson(route('expenses.categories.index'))
            ->assertForbidden();
    }

    public function test_categories_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('expenses.categories.store'), ['name' => 'Cafetería'])
            ->assertOk();

        $this->assertDatabaseHas('expense_categories', ['name' => 'Cafetería']);

        $category = ExpenseCategory::where('name', 'Cafetería')->firstOrFail();

        $this->actingAs($this->user)
            ->putJson(route('expenses.categories.update', $category), [
                'name' => 'Cafetería y snacks',
                'is_active' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Cafetería y snacks',
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->deleteJson(route('expenses.categories.destroy', $category))
            ->assertOk();

        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_categories_require_a_unique_name(): void
    {
        ExpenseCategory::factory()->create(['name' => 'Transporte']);

        $this->actingAs($this->user)
            ->postJson(route('expenses.categories.store'), ['name' => 'Transporte'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_creating_a_category_is_forbidden_without_manage_permission(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('expenses.index');

        $this->actingAs($viewer)
            ->postJson(route('expenses.categories.store'), ['name' => 'Sin permiso'])
            ->assertForbidden();
    }

    // --- receipt ---

    public function test_store_attaches_multiple_receipt_files(): void
    {
        Storage::fake('public');

        $category = ExpenseCategory::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'concept' => 'Compra de herramienta',
                'amount' => 1800,
                'expense_date' => '2026-09-15',
                'status' => 'pending',
                'receipts' => [
                    UploadedFile::fake()->image('comprobante.jpg'),
                    UploadedFile::fake()->create('factura.pdf', 40, 'application/pdf'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $expense = Expense::where('concept', 'Compra de herramienta')->firstOrFail();

        $this->assertCount(2, $expense->getMedia('receipt'));
        $this->assertSame('comprobante.jpg', $expense->receipt_name);
        $this->assertCount(2, $expense->receipts);
    }

    public function test_store_rejects_an_invalid_receipt_file(): void
    {
        Storage::fake('public');

        $category = ExpenseCategory::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'concept' => 'Archivo inválido',
                'amount' => 100,
                'expense_date' => '2026-09-15',
                'status' => 'pending',
                'receipts' => [UploadedFile::fake()->create('documento.docx', 100)],
            ])
            ->assertSessionHasErrors('receipts.0');
    }

    public function test_update_appends_receipt_files(): void
    {
        Storage::fake('public');

        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create();

        $expense->addMedia(UploadedFile::fake()->create('recibo-viejo.pdf', 50, 'application/pdf'))
            ->toMediaCollection('receipt');

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => $expense->concept,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'status' => 'pending',
                'receipts' => [UploadedFile::fake()->image('recibo-nuevo.jpg')],
            ])
            ->assertRedirect();

        $expense->refresh();

        $names = $expense->getMedia('receipt')->pluck('file_name');

        $this->assertCount(2, $names);
        $this->assertTrue($names->contains('recibo-viejo.pdf'));
        $this->assertTrue($names->contains('recibo-nuevo.jpg'));
    }

    public function test_update_can_remove_a_receipt_file(): void
    {
        Storage::fake('public');

        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create();

        $media = $expense->addMedia(UploadedFile::fake()->create('recibo.pdf', 50, 'application/pdf'))
            ->toMediaCollection('receipt');

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => $expense->concept,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'status' => 'pending',
                'remove_receipt_ids' => [$media->id],
            ])
            ->assertRedirect();

        $this->assertCount(0, $expense->fresh()->getMedia('receipt'));
    }

    public function test_update_accepts_form_data_with_method_spoofing(): void
    {
        // The edit dialog sends the update as POST with "_method=put" because PHP does not
        // parse multipart bodies on real PUT requests (needed for receipt uploads).
        Storage::fake('public');

        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.update', $expense), [
                '_method' => 'put',
                'expense_category_id' => $category->id,
                'concept' => 'Gasto actualizado desde el formulario',
                'amount' => 1200.50,
                'expense_date' => '2026-09-12',
                'status' => 'pending',
                'receipts' => [UploadedFile::fake()->image('nuevo-comprobante.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $expense->refresh();

        $this->assertSame('Gasto actualizado desde el formulario', $expense->concept);
        $this->assertSame('1200.50', $expense->amount);
        $this->assertTrue($expense->hasMedia('receipt'));
    }

    // --- new index filters ---

    public function test_index_filters_expenses_by_payment_method(): void
    {
        $card = Expense::factory()->create(['payment_method' => 'card']);
        Expense::factory()->create(['payment_method' => 'cash']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['payment_method' => 'card']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $card->id)
                ->where('stats.total_count', 1)
            );
    }

    public function test_index_filters_expenses_by_folio(): void
    {
        $expense = Expense::factory()->create(['folio' => 'GAS-9001']);
        Expense::factory()->create(['folio' => 'GAS-9002']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['folio' => 'GAS-9001']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $expense->id)
            );
    }

    public function test_index_filters_expenses_by_budget(): void
    {
        $budget = Budget::factory()->create();
        $linked = Expense::factory()->create(['budget_id' => $budget->id]);
        Expense::factory()->create(['budget_id' => null]);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['budget_id' => $budget->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $linked->id)
            );
    }

    public function test_index_filters_expenses_by_type(): void
    {
        Expense::factory()->create(['budget_id' => null]);
        Expense::factory()->create(['budget_id' => Budget::factory()->create()->id]);
        $commission = Expense::factory()->create([
            'budget_id' => Budget::factory()->create()->id,
            'is_commission' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['type' => 'commission']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $commission->id)
            );

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['type' => 'general']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
            );
    }

    public function test_index_sorts_expenses_by_budget(): void
    {
        $budgetA = Budget::factory()->create();
        $budgetB = Budget::factory()->create();

        // Default order (newest first) would show B before A
        $a = Expense::factory()->create(['budget_id' => $budgetA->id, 'expense_date' => '2026-08-01']);
        $b = Expense::factory()->create(['budget_id' => $budgetB->id, 'expense_date' => '2026-09-01']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['sort_by' => 'budget_id', 'sort_dir' => 'asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 2)
                ->where('expenses.data.0.id', $a->id)
                ->where('expenses.data.1.id', $b->id)
            );
    }

    public function test_index_sorts_expenses_by_folio(): void
    {
        $second = Expense::factory()->create(['folio' => 'GAS-0002']);
        $first = Expense::factory()->create(['folio' => 'GAS-0001']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['sort_by' => 'folio', 'sort_dir' => 'asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 2)
                ->where('expenses.data.0.id', $first->id)
                ->where('expenses.data.1.id', $second->id)
            );
    }

    public function test_index_sorts_expenses_by_date(): void
    {
        $newer = Expense::factory()->create(['expense_date' => '2026-09-10']);
        $older = Expense::factory()->create(['expense_date' => '2026-08-01']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['sort_by' => 'expense_date', 'sort_dir' => 'asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 2)
                ->where('expenses.data.0.id', $older->id)
                ->where('expenses.data.1.id', $newer->id)
            );
    }

    public function test_index_sorts_expenses_by_status_in_workflow_order(): void
    {
        $cancelled = Expense::factory()->cancelled()->create();
        $paid = Expense::factory()->paid()->create();
        $pending = Expense::factory()->pending()->create();

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['sort_by' => 'status', 'sort_dir' => 'asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 3)
                ->where('expenses.data.0.id', $pending->id)
                ->where('expenses.data.1.id', $paid->id)
                ->where('expenses.data.2.id', $cancelled->id)
            );
    }

    public function test_index_passes_budgets_with_expenses_for_the_filter(): void
    {
        $budget = Budget::factory()->create();
        Expense::factory()->create(['budget_id' => $budget->id]);
        Expense::factory()->create(['budget_id' => null]);

        $this->actingAs($this->user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('budgets', 1)
                ->where('budgets.0.id', $budget->id)
                ->has('budgets.0.folio')
            );
    }

    // --- budget expenses panel ---

    public function test_budget_expenses_panel_renders_the_breakdown_and_extras(): void
    {
        $budget = Budget::factory()->create();
        BudgetConcept::factory()->create([
            'budget_id' => $budget->id,
            'concept' => 'Andamios y elevación',
            'amount' => 15000,
        ]);
        Expense::factory()->pending()->create([
            'budget_id' => $budget->id,
            'concept' => 'Renta de grúa',
            'amount' => 3000,
        ]);
        Expense::factory()->pending()->create([
            'budget_id' => $budget->id,
            'concept' => 'Comisión de venta',
            'amount' => 900,
            'is_commission' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('expenses.budgets.show', $budget))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/BudgetExpenses')
                ->has('budget.concepts', 1)
                ->where('budget.concepts.0.concept', 'Andamios y elevación')
                ->where('budget.concepts.0.expense', null)
                ->has('budget.extras', 2)
                ->has('categories')
            );

        $panel = app(BudgetExpenseService::class)->getPanelData($budget->fresh());

        $this->assertSame(15000.0, $panel['totals']['concepts']);
        $this->assertSame(3900.0, $panel['totals']['extras']);
        $this->assertSame(0.0, $panel['totals']['paid']);
        $this->assertSame(18900.0, $panel['totals']['pending']);
    }

    public function test_budget_expenses_panel_shows_linked_concept_expenses(): void
    {
        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create(['budget_id' => $budget->id]);
        Expense::factory()->paid()->create([
            'budget_id' => $budget->id,
            'budget_concept_id' => $concept->id,
            'concept' => $concept->concept,
            'amount' => $concept->amount,
        ]);

        $this->actingAs($this->user)
            ->get(route('expenses.budgets.show', $budget))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('budget.concepts.0.expense.status', 'paid')
                ->has('budget.concepts.0.expense.receipts')
                ->has('budget.concepts.0.expense.folio')
            );
    }

    public function test_budget_expenses_panel_requires_index_permission(): void
    {
        $budget = Budget::factory()->create();
        $viewer = User::factory()->create(['is_active' => true]);

        $this->actingAs($viewer)
            ->get(route('expenses.budgets.show', $budget))
            ->assertForbidden();
    }

    // --- budget concept payments ---

    public function test_concept_payment_registers_a_paid_expense_and_syncs_the_concept(): void
    {
        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create([
            'budget_id' => $budget->id,
            'concept' => 'Mano de obra',
            'amount' => 5000,
        ]);

        $this->actingAs($this->user)
            ->post(route('expenses.budgets.concepts.payment', [$budget, $concept]), [
                'status' => 'paid',
                'payment_date' => '2026-09-15',
                'reference' => 'TRF-8891',
                'notes' => 'Pago de mano de obra',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $expense = Expense::where('budget_concept_id', $concept->id)->firstOrFail();

        $this->assertSame($budget->id, $expense->budget_id);
        $this->assertSame('Mano de obra', $expense->concept);
        $this->assertSame('5000.00', $expense->amount);
        $this->assertSame('paid', $expense->status);
        $this->assertTrue($expense->is_commission === false);
        $this->assertNotNull($expense->paid_at);
        $this->assertSame('2026-09-15', $concept->fresh()->payment_date->format('Y-m-d'));
    }

    public function test_concept_payment_can_be_edited_back_to_pending(): void
    {
        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create([
            'budget_id' => $budget->id,
            'payment_date' => '2026-09-01',
        ]);
        $expense = Expense::factory()->paid()->create([
            'budget_id' => $budget->id,
            'budget_concept_id' => $concept->id,
            'concept' => $concept->concept,
            'amount' => $concept->amount,
        ]);

        $this->actingAs($this->user)
            ->post(route('expenses.budgets.concepts.payment', [$budget, $concept]), [
                'status' => 'pending',
                'payment_date' => '2026-09-16',
            ])
            ->assertRedirect();

        $expense->refresh();

        $this->assertSame('pending', $expense->status);
        $this->assertNull($expense->paid_at);
        $this->assertNull($concept->fresh()->payment_date);
    }

    public function test_concept_payment_attaches_receipts(): void
    {
        Storage::fake('public');

        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create(['budget_id' => $budget->id]);

        $this->actingAs($this->user)
            ->post(route('expenses.budgets.concepts.payment', [$budget, $concept]), [
                'status' => 'paid',
                'payment_date' => '2026-09-15',
                'receipts' => [
                    UploadedFile::fake()->image('factura.jpg'),
                    UploadedFile::fake()->create('transferencia.pdf', 30, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $expense = Expense::where('budget_concept_id', $concept->id)->firstOrFail();

        $this->assertCount(2, $expense->getMedia('receipt'));
    }

    public function test_concept_payment_rejects_a_concept_from_another_budget(): void
    {
        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.budgets.concepts.payment', [$budget, $concept]), [
                'status' => 'paid',
                'payment_date' => '2026-09-15',
            ])
            ->assertNotFound();
    }

    public function test_concept_payment_is_forbidden_without_expense_write_permission(): void
    {
        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create(['budget_id' => $budget->id]);
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('expenses.index');

        $this->actingAs($viewer)
            ->post(route('expenses.budgets.concepts.payment', [$budget, $concept]), [
                'status' => 'paid',
                'payment_date' => '2026-09-15',
            ])
            ->assertForbidden();
    }

    public function test_bulk_mark_concepts_paid_registers_each_expense(): void
    {
        $budget = Budget::factory()->create();
        $first = BudgetConcept::factory()->create(['budget_id' => $budget->id, 'amount' => 1000]);
        $second = BudgetConcept::factory()->create(['budget_id' => $budget->id, 'amount' => 2500]);
        $fromAnotherBudget = BudgetConcept::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.budgets.concepts.mark-paid', $budget), [
                'concepts' => [$first->id, $second->id, $fromAnotherBudget->id],
                'payment_date' => '2026-09-15',
                'reference' => 'TRF-0001',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Expense::where('budget_id', $budget->id)->count());
        $this->assertDatabaseMissing('expenses', ['budget_concept_id' => $fromAnotherBudget->id]);
        $this->assertSame('2026-09-15', $first->fresh()->payment_date->format('Y-m-d'));
        $this->assertSame('2026-09-15', $second->fresh()->payment_date->format('Y-m-d'));
    }

    // --- commissions (payment channel fees) ---

    public function test_store_creates_a_general_expense_with_commission(): void
    {
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'concept' => 'Pago en OXXO de insumos',
                'amount' => 1000,
                'commission_amount' => 25.50,
                'expense_date' => '2026-09-15',
                'status' => 'paid',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $expense = Expense::where('concept', 'Pago en OXXO de insumos')->firstOrFail();

        $this->assertSame('25.50', $expense->commission_amount);
    }

    public function test_update_can_change_and_clear_the_commission(): void
    {
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create(['commission_amount' => 10]);

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => $expense->concept,
                'amount' => $expense->amount,
                'commission_amount' => 30,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'status' => 'pending',
            ])
            ->assertRedirect();

        $this->assertSame('30.00', $expense->fresh()->commission_amount);

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => $expense->concept,
                'amount' => $expense->amount,
                'commission_amount' => null,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'status' => 'pending',
            ])
            ->assertRedirect();

        $this->assertNull($expense->fresh()->commission_amount);
    }

    public function test_concept_payment_registers_the_commission_on_the_expense(): void
    {
        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create(['budget_id' => $budget->id]);

        $this->actingAs($this->user)
            ->post(route('expenses.budgets.concepts.payment', [$budget, $concept]), [
                'status' => 'paid',
                'payment_date' => '2026-09-15',
                'commission_amount' => 15,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $expense = Expense::where('budget_concept_id', $concept->id)->firstOrFail();

        $this->assertSame('15.00', $expense->commission_amount);
    }

    public function test_bulk_mark_concepts_paid_registers_a_transaction_commission(): void
    {
        $budget = Budget::factory()->create();
        $first = BudgetConcept::factory()->create(['budget_id' => $budget->id, 'amount' => 1000]);
        $second = BudgetConcept::factory()->create(['budget_id' => $budget->id, 'amount' => 2000]);

        $this->actingAs($this->user)
            ->post(route('expenses.budgets.concepts.mark-paid', $budget), [
                'concepts' => [$first->id, $second->id],
                'payment_date' => '2026-09-15',
                'commission_amount' => 40,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Expense::where('budget_id', $budget->id)->whereNotNull('budget_concept_id')->count());

        $commission = Expense::where('budget_id', $budget->id)->where('is_commission', true)->firstOrFail();

        $this->assertSame('Comisión de pago', $commission->concept);
        $this->assertSame('40.00', $commission->amount);
        $this->assertSame('paid', $commission->status);
        $this->assertNull($commission->budget_concept_id);
    }

    public function test_index_summary_includes_commissions(): void
    {
        Expense::factory()->pending()->create(['amount' => 100, 'commission_amount' => 10]);
        Expense::factory()->paid()->create(['amount' => 200, 'commission_amount' => 20]);

        $this->actingAs($this->user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_amount', 330)
                ->where('stats.pending_amount', 110)
                ->where('stats.paid_amount', 220)
            );
    }

    public function test_budget_expenses_totals_include_commissions(): void
    {
        $budget = Budget::factory()->create();
        $concept = BudgetConcept::factory()->create(['budget_id' => $budget->id, 'amount' => 1000]);

        Expense::factory()->paid()->create([
            'budget_id' => $budget->id,
            'budget_concept_id' => $concept->id,
            'concept' => $concept->concept,
            'amount' => 1000,
            'commission_amount' => 20,
        ]);

        $panel = app(BudgetExpenseService::class)->getPanelData($budget->fresh());

        $this->assertSame(1020.0, $panel['totals']['paid']);
        $this->assertSame(0.0, $panel['totals']['pending']);

        // Pending extras carry their commission too.
        Expense::factory()->pending()->create([
            'budget_id' => $budget->id,
            'amount' => 500,
            'commission_amount' => 15,
        ]);

        $panel = app(BudgetExpenseService::class)->getPanelData($budget->fresh());

        $this->assertSame(1020.0, $panel['totals']['paid']);
        $this->assertSame(515.0, $panel['totals']['pending']);
    }

    public function test_export_rows_include_commission(): void
    {
        Expense::factory()->create(['amount' => 100, 'commission_amount' => 12.5]);

        $rows = app(ExpenseService::class)->getExportRows(['status' => 'pending']);

        $this->assertCount(1, $rows);
        $this->assertSame(12.5, $rows->first()['commission_amount']);
    }

    // --- excel export ---

    public function test_export_downloads_an_xlsx_report_with_the_applied_filters(): void
    {
        Expense::factory()->paid()->create();
        Expense::factory()->pending()->create();

        $response = $this->actingAs($this->user)
            ->get(route('expenses.export', ['status' => 'paid']));

        $response->assertOk();
        $response->assertDownload('reporte-gastos-' . now()->format('Y-m-d') . '.xlsx');
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }

    public function test_export_is_forbidden_without_permission(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);

        $this->actingAs($viewer)
            ->get(route('expenses.export'))
            ->assertForbidden();
    }

    public function test_export_rows_respect_the_applied_filters(): void
    {
        $paid = Expense::factory()->paid()->create();
        Expense::factory()->pending()->create();

        $rows = app(ExpenseService::class)->getExportRows(['status' => 'paid']);

        $this->assertCount(1, $rows);
        $this->assertSame($paid->id, $rows->first()['id']);
    }

    public function test_xlsx_writer_generates_a_readable_workbook(): void
    {
        $path = app(XlsxWriterService::class)->generate(
            'Prueba',
            ['Concepto', 'Monto'],
            [['Renta & servicios', 1234.5]],
            [20, 12],
            ['Total', 1234.5],
        );

        $this->assertFileExists($path);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));

        $parts = [
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ];

        $contents = [];

        foreach ($parts as $part) {
            $contents[$part] = $zip->getFromName($part);
        }

        $zip->close();

        foreach ($parts as $part) {
            $this->assertNotFalse($contents[$part], "Missing workbook part: {$part}");
            $this->assertNotFalse(
                simplexml_load_string($contents[$part]),
                "The part {$part} is not valid XML."
            );
        }

        $this->assertStringContainsString('Prueba', $contents['xl/workbook.xml']);
        $this->assertStringContainsString('Renta &amp; servicios', $contents['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('1234.5', $contents['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('Total', $contents['xl/worksheets/sheet1.xml']);

        unlink($path);
    }
}
