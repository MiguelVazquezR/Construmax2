<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

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
        Expense::factory()->pending()->create(['amount' => 1000, 'concept' => 'Renta de oficina septiembre']);
        Expense::factory()->paid()->create(['amount' => 2500.50, 'concept' => 'Servicio de internet']);
        Expense::factory()->pending()->create([
            'amount' => 500,
            'concept' => 'Insumos de obra',
            'ticket_id' => Ticket::factory(),
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
                ->where('stats.project_count', 1)
                ->where('stats.project_amount', 500)
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

    public function test_store_creates_a_ticket_linked_expense(): void
    {
        $category = ExpenseCategory::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'ticket_id' => $ticket->id,
                'concept' => 'Compra de material para el proyecto',
                'amount' => 1200,
                'expense_date' => '2026-09-15',
                'status' => 'paid',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $expense = Expense::where('concept', 'Compra de material para el proyecto')->firstOrFail();

        $this->assertSame($ticket->id, $expense->ticket_id);
        $this->assertSame('paid', $expense->status);
        $this->assertNotNull($expense->paid_at);
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

    // --- ticket search ---

    public function test_ticket_search_returns_matching_tickets(): void
    {
        $ticket = Ticket::factory()->create(['name' => 'Instalación eléctrica nave 3']);

        $this->actingAs($this->user)
            ->getJson(route('expenses.tickets.search', ['q' => 'Instalación eléctrica']))
            ->assertOk()
            ->assertJsonFragment(['id' => $ticket->id]);
    }

    public function test_ticket_search_requires_expense_write_permission(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('expenses.index');

        $this->actingAs($viewer)
            ->getJson(route('expenses.tickets.search'))
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

    public function test_store_attaches_a_receipt_file(): void
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
                'receipt' => UploadedFile::fake()->image('comprobante.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $expense = Expense::where('concept', 'Compra de herramienta')->firstOrFail();

        $this->assertTrue($expense->hasMedia('receipt'));
        $this->assertSame('comprobante.jpg', $expense->receipt_name);
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
                'receipt' => UploadedFile::fake()->create('documento.docx', 100),
            ])
            ->assertSessionHasErrors('receipt');
    }

    public function test_update_replaces_the_receipt_file(): void
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
                'receipt' => UploadedFile::fake()->image('recibo-nuevo.jpg'),
            ])
            ->assertRedirect();

        $expense->refresh();

        $this->assertCount(1, $expense->getMedia('receipt'));
        $this->assertSame('recibo-nuevo.jpg', $expense->getFirstMedia('receipt')->file_name);
    }

    public function test_update_can_remove_the_receipt_file(): void
    {
        Storage::fake('public');

        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create();

        $expense->addMedia(UploadedFile::fake()->create('recibo.pdf', 50, 'application/pdf'))
            ->toMediaCollection('receipt');

        $this->actingAs($this->user)
            ->put(route('expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'concept' => $expense->concept,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'status' => 'pending',
                'remove_receipt' => true,
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
                'receipt' => UploadedFile::fake()->image('nuevo-comprobante.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $expense->refresh();

        $this->assertSame('Gasto actualizado desde el formulario', $expense->concept);
        $this->assertSame('1200.50', $expense->amount);
        $this->assertTrue($expense->hasMedia('receipt'));
    }
}
