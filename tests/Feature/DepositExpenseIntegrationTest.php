<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Deposit;
use App\Models\DepositType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Technician;
use App\Models\TechnicianBankAccount;
use App\Models\TechnicianPayment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DepositExpenseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Ticket $ticket;

    private Budget $budget;

    private Technician $technician;

    private TechnicianBankAccount $bankAccount;

    private DepositType $depositType;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'expenses.index' => 'View expenses',
            'expenses.create' => 'Create expenses',
            'expenses.edit' => 'Edit expenses',
            'expenses.delete' => 'Delete expenses',
            'deposits.approve' => 'Approve deposits',
            'deposits.edit' => 'Edit deposits',
        ] as $name => $description) {
            Permission::create([
                'name' => $name,
                'guard_name' => 'web',
                'category' => 'Control de gastos',
                'description' => $description,
            ]);
        }

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->givePermissionTo([
            'expenses.index',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            'deposits.approve',
            'deposits.edit',
        ]);

        $this->ticket = Ticket::factory()->create();
        $this->budget = Budget::factory()->create(['ticket_id' => $this->ticket->id]);
        $this->technician = Technician::create(['user_id' => User::factory()->create()->id]);
        $this->bankAccount = TechnicianBankAccount::create([
            'technician_id' => $this->technician->id,
            'bank_name' => 'BBVA',
        ]);
        $this->depositType = DepositType::create(['name' => 'Anticipo']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createTicketDeposit(array $overrides = []): Deposit
    {
        $this->actingAs($this->user)
            ->post(route('deposits.store'), array_merge([
                'deposit_type_id' => $this->depositType->id,
                'technician_id' => $this->technician->id,
                'technician_bank_account_id' => $this->bankAccount->id,
                'ticket_id' => $this->ticket->id,
                'amount' => 1500,
                'shift' => 'matutino',
                'scheduled_date' => '2026-09-18',
            ], $overrides))
            ->assertRedirect();

        return Deposit::latest('id')->firstOrFail();
    }

    private function approveDeposit(Deposit $deposit): void
    {
        $this->actingAs($this->user)
            ->post(route('deposits.approve', $deposit))
            ->assertRedirect();
    }

    // --- deposits → expenses ---

    public function test_creating_a_deposit_does_not_mirror_it_until_it_is_approved(): void
    {
        $deposit = $this->createTicketDeposit();

        $this->assertDatabaseMissing('expenses', ['deposit_id' => $deposit->id]);
    }

    public function test_approving_a_deposit_creates_the_pending_mirror_expense(): void
    {
        $deposit = $this->createTicketDeposit();

        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->assertSame($this->budget->id, $expense->budget_id);
        $this->assertSame('1500.00', $expense->amount);
        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        $this->assertSame('Pendiente de depósito', $expense->status_label);
        $this->assertSame(
            'Depósito: Anticipo — ' . $this->technician->user->name,
            $expense->concept
        );
        $this->assertSame('Depósito #' . $deposit->id, $expense->reference);
        $this->assertSame($this->user->id, $expense->created_by);
        $this->assertCount(0, $expense->receipts);
    }

    public function test_completing_a_deposit_with_a_voucher_marks_the_mirror_expense_as_paid(): void
    {
        Storage::fake('public');

        $deposit = $this->createTicketDeposit();

        $this->actingAs($this->user)->post(route('deposits.approve', $deposit))->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('deposits.complete', $deposit), [
                'commission_amount' => 25,
                'voucher' => UploadedFile::fake()->image('voucher.jpg'),
            ])
            ->assertRedirect();

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->assertSame('completed', $deposit->fresh()->status);
        $this->assertSame(Expense::STATUS_PAID, $expense->status);
        $this->assertSame('25.00', $expense->commission_amount);
        $this->assertNotNull($expense->paid_at);
        $this->assertCount(1, $expense->getMedia('receipt'));
        $this->assertSame('voucher.jpg', $expense->receipt_name);
        $this->assertTrue(
            TechnicianPayment::where('budget_id', $this->budget->id)
                ->where('user_id', $this->technician->user_id)
                ->exists()
        );
    }

    public function test_updating_a_deposit_updates_the_mirror_expense(): void
    {
        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        $this->actingAs($this->user)
            ->put(route('deposits.update', $deposit), [
                'deposit_type_id' => $this->depositType->id,
                'technician_id' => $this->technician->id,
                'technician_bank_account_id' => $this->bankAccount->id,
                'ticket_id' => $this->ticket->id,
                'amount' => 2000,
                'shift' => 'vespertino',
                'scheduled_date' => '2026-09-19',
            ])
            ->assertRedirect();

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->assertSame('2000.00', $expense->amount);
        $this->assertSame('2026-09-19', $expense->expense_date->format('Y-m-d'));
    }

    public function test_deleting_a_deposit_deletes_the_mirror_expense(): void
    {
        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('deposits.destroy', $deposit))
            ->assertRedirect();

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_external_deposits_are_mirrored_as_general_expenses(): void
    {
        $this->actingAs($this->user)
            ->post(route('deposits.store'), [
                'is_external' => true,
                'deposit_type_id' => $this->depositType->id,
                'external_beneficiary_name' => 'Proveedor externo',
                'external_account_number' => '1234567890',
                'amount' => 800,
                'shift' => 'matutino',
                'scheduled_date' => '2026-09-18',
            ])
            ->assertRedirect();

        $deposit = Deposit::latest('id')->firstOrFail();
        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->assertNull($expense->budget_id);
        $this->assertSame('Depósito externo: Anticipo — Proveedor externo', $expense->concept);
    }

    // --- expenses → deposits ---

    public function test_completing_the_deposit_from_expenses_syncs_both_modules(): void
    {
        Storage::fake('public');

        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('expenses.complete-deposit', $expense), [
                'commission_amount' => 15,
                'voucher' => UploadedFile::fake()->image('comprobante.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $deposit->refresh();
        $expense->refresh();

        $this->assertSame('completed', $deposit->status);
        $this->assertNotNull($deposit->getFirstMedia('voucher'));
        $this->assertSame(Expense::STATUS_PAID, $expense->status);
        $this->assertSame('15.00', $expense->commission_amount);
        $this->assertCount(1, $expense->getMedia('receipt'));
        $this->assertTrue(TechnicianPayment::where('budget_id', $this->budget->id)->exists());
    }

    public function test_completing_the_deposit_from_expenses_requires_a_voucher(): void
    {
        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('expenses.complete-deposit', $expense), ['commission_amount' => 10])
            ->assertSessionHasErrors('voucher');

        $this->assertSame('approved', $deposit->fresh()->status);
        $this->assertSame(Expense::STATUS_PENDING, $expense->fresh()->status);
    }

    public function test_completing_the_deposit_from_expenses_is_forbidden_without_permission(): void
    {
        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('expenses.index');

        $this->actingAs($viewer)
            ->post(route('expenses.complete-deposit', $expense), ['commission_amount' => 10])
            ->assertForbidden();
    }

    public function test_completing_the_deposit_from_expenses_is_allowed_with_deposits_approve_permission(): void
    {
        Storage::fake('public');

        $deposit = $this->createTicketDeposit();

        $approver = User::factory()->create(['is_active' => true]);
        $approver->givePermissionTo('deposits.approve');

        $this->actingAs($approver)->post(route('deposits.approve', $deposit))->assertRedirect();

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->actingAs($approver)
            ->post(route('expenses.complete-deposit', $expense), [
                'voucher' => UploadedFile::fake()->image('comprobante.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $deposit->fresh()->status);
    }

    public function test_mark_paid_and_delete_are_blocked_for_deposit_expenses(): void
    {
        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('expenses.mark-paid', $expense))
            ->assertSessionHas('error');

        $this->assertSame(Expense::STATUS_PENDING, $expense->fresh()->status);

        $this->actingAs($this->user)
            ->delete(route('expenses.destroy', $expense))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_deposit_expense_edit_keeps_the_values_managed_by_the_deposit(): void
    {
        $category = ExpenseCategory::factory()->create();

        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        $expense = Expense::where('deposit_id', $deposit->id)->firstOrFail();
        $originalConcept = $expense->concept;

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => 'Intento de cambio',
                'amount' => 9999,
                'expense_date' => '2020-01-01',
                'status' => 'paid',
                'payment_method' => 'cash',
                'reference' => 'REF-NUEVA',
            ])
            ->assertRedirect();

        $expense->refresh();

        $this->assertSame($originalConcept, $expense->concept);
        $this->assertSame('1500.00', $expense->amount);
        $this->assertSame('2026-09-18', $expense->expense_date->format('Y-m-d'));
        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        $this->assertSame('REF-NUEVA', $expense->reference);
        $this->assertSame($category->id, $expense->expense_category_id);
    }

    // --- index filter ---

    public function test_index_filters_deposit_expenses_by_type(): void
    {
        $deposit = $this->createTicketDeposit();
        $this->approveDeposit($deposit);

        Expense::factory()->create(['budget_id' => null]);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['type' => 'deposit']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.deposit_id', $deposit->id)
            );
    }
}
