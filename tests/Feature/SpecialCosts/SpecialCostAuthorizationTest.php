<?php

namespace Tests\Feature\SpecialCosts;

use App\Models\Budget;
use App\Models\BudgetCatalog;
use App\Models\BudgetCatalogItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SpecialCostAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->approver = User::factory()->create(['is_active' => true]);

        Permission::create(['name' => 'costs.index', 'guard_name' => 'web', 'category' => 'Costs', 'description' => 'Access costs']);
        Permission::create(['name' => 'costs.transfer', 'guard_name' => 'web', 'category' => 'Costs', 'description' => 'Transfer to special']);
        Permission::create(['name' => 'special-costs.index', 'guard_name' => 'web', 'category' => 'Costos especiales', 'description' => 'View special costs']);
        Permission::create(['name' => 'special-costs.approve', 'guard_name' => 'web', 'category' => 'Costos especiales', 'description' => 'Approve special costs']);
        Permission::create(['name' => 'special-costs.create-version', 'guard_name' => 'web', 'category' => 'Costos especiales', 'description' => 'Create version in special costs']);

        $this->user->givePermissionTo('costs.index', 'costs.transfer');
        $this->approver->givePermissionTo('special-costs.index', 'special-costs.approve', 'special-costs.create-version');
    }

    // --- BudgetCatalog model fields ---

    public function test_budget_catalog_has_special_authorization_fields(): void
    {
        $catalog = BudgetCatalog::factory()->create([
            'needs_special_authorization' => true,
            'transfer_notes' => 'Requiere revision de Direccion',
        ]);

        $this->assertTrue($catalog->needs_special_authorization);
        $this->assertSame('Requiere revision de Direccion', $catalog->transfer_notes);
    }

    // --- transferToSpecial ---

    public function test_transfer_to_special_requires_permission(): void
    {
        $ticket = Ticket::factory()->create();
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        $noPermUser = User::factory()->create();

        $this->actingAs($noPermUser)
            ->post(route('costs.transfer-to-special', ['budget' => $budget->id, 'catalog' => $catalog->id]), [
                'transfer_notes' => 'Test notes',
            ])
            ->assertForbidden();
    }

    public function test_transfer_to_special_requires_notes(): void
    {
        $ticket = Ticket::factory()->create();
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        $this->actingAs($this->user)
            ->post(route('costs.transfer-to-special', ['budget' => $budget->id, 'catalog' => $catalog->id]), [])
            ->assertSessionHasErrors(['transfer_notes']);
    }

    public function test_transfer_to_special_sets_flags_and_does_not_change_ticket_status(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Pendiente de aprobación']);
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
            'needs_special_authorization' => false,
        ]);

        $this->actingAs($this->user)
            ->post(route('costs.transfer-to-special', ['budget' => $budget->id, 'catalog' => $catalog->id]), [
                'transfer_notes' => 'Direccion debe revisar este catalogo',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('budget_catalogs', [
            'id' => $catalog->id,
            'needs_special_authorization' => true,
            'transfer_notes' => 'Direccion debe revisar este catalogo',
        ]);

        // Ticket status must NOT change
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'Pendiente de aprobación',
        ]);
    }

    // --- special-costs.index ---

    public function test_special_costs_index_requires_permission(): void
    {
        $noPermUser = User::factory()->create();

        $this->actingAs($noPermUser)
            ->get(route('special-costs.index'))
            ->assertForbidden();
    }

    public function test_special_costs_index_renders_page(): void
    {
        $this->actingAs($this->approver)
            ->get(route('special-costs.index'))
            ->assertInertia(fn ($page) => $page
                ->component('SpecialCosts/Index')
                ->has('catalogs')
            );
    }

    public function test_special_costs_index_only_shows_flagged_pending_catalogs(): void
    {
        $ticket = Ticket::factory()->create();
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);

        // Should be visible: flagged + pending
        $visible = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'needs_special_authorization' => true,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        // Should NOT be visible: not flagged
        BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'needs_special_authorization' => false,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        // Should NOT be visible: flagged but already approved
        BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'needs_special_authorization' => true,
            'status' => BudgetCatalog::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($this->approver)
            ->get(route('special-costs.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('SpecialCosts/Index')
            ->has('catalogs.data', 1)
            ->where('catalogs.data.0.id', $visible->id)
        );
    }

    // --- special-costs.show ---

    public function test_special_costs_show_404_if_not_flagged(): void
    {
        $ticket = Ticket::factory()->create();
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'needs_special_authorization' => false,
        ]);

        $this->actingAs($this->approver)
            ->get(route('special-costs.show', $catalog))
            ->assertNotFound();
    }

    public function test_special_costs_show_renders_details(): void
    {
        $ticket = Ticket::factory()->create();
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'needs_special_authorization' => true,
            'transfer_notes' => 'Notas de prueba',
        ]);

        $this->actingAs($this->approver)
            ->get(route('special-costs.show', $catalog))
            ->assertInertia(fn ($page) => $page
                ->component('SpecialCosts/Show')
                ->has('catalog')
                ->has('ticket')
                ->has('latest_catalog')
                ->where('catalog.transfer_notes', 'Notas de prueba')
            );
    }

    // --- storeCatalog (create new version from special costs) ---

    public function test_store_catalog_creates_new_version_and_clears_old_flag(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Pendiente de aprobación']);
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $oldCatalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'version' => 1,
            'needs_special_authorization' => true,
            'transfer_notes' => 'Original notes',
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        $data = [
            'subtotal' => 1000,
            'iva' => 160,
            'total' => 1160,
            'items' => [
                [
                    'description' => 'Material nuevo',
                    'unit' => 'PZA',
                    'quantity' => 10,
                    'unit_price' => 100,
                    'total' => 1000,
                ],
            ],
        ];

        $this->actingAs($this->approver)
            ->post(route('special-costs.store-catalog', $oldCatalog), $data)
            ->assertRedirect();

        // New catalog created with version 2
        $this->assertDatabaseHas('budget_catalogs', [
            'budget_id' => $budget->id,
            'version' => 2,
            'needs_special_authorization' => true,
            'transfer_notes' => 'Original notes',
            'total' => 1160,
        ]);

        // Old catalog flag cleared
        $this->assertDatabaseHas('budget_catalogs', [
            'id' => $oldCatalog->id,
            'needs_special_authorization' => false,
        ]);

        $this->assertDatabaseCount('budget_catalog_items', 1);
    }

    // --- approveCatalog from special costs ---

    public function test_approve_catalog_from_special_costs(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Pendiente de aprobación']);
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'needs_special_authorization' => true,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        $this->actingAs($this->approver)
            ->post(route('special-costs.approve-catalog', $catalog))
            ->assertRedirect(route('special-costs.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('budget_catalogs', [
            'id' => $catalog->id,
            'status' => BudgetCatalog::STATUS_APPROVED,
            'approved_by' => $this->approver->id,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'Catálogo',
        ]);
    }

    public function test_approve_catalog_requires_permission(): void
    {
        $ticket = Ticket::factory()->create();
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        $catalog = BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'needs_special_authorization' => true,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        $noPermUser = User::factory()->create();

        $this->actingAs($noPermUser)
            ->post(route('special-costs.approve-catalog', $catalog))
            ->assertForbidden();
    }

    // --- Costs index shows transferred status ---

    public function test_costs_index_includes_needs_special_authorization_flag(): void
    {
        $ticket = Ticket::factory()->create();
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);
        BudgetCatalog::factory()->create([
            'budget_id' => $budget->id,
            'version' => 1,
            'needs_special_authorization' => true,
            'status' => BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        $this->actingAs($this->user)
            ->get(route('costs.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Costs/Index')
                ->has('budgets.data', 1)
                ->where('budgets.data.0.needs_special_authorization', true)
            );
    }

    public function test_costs_index_includes_can_transfer_prop(): void
    {
        $this->actingAs($this->user)
            ->get(route('costs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('canTransfer', true)
            );
    }
}