<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetCatalog;
use App\Models\BudgetCatalogItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CostControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
        Permission::create(['name' => 'costs.index', 'guard_name' => 'web', 'category' => 'Costs', 'description' => 'Access costs']);
        $this->user->givePermissionTo('costs.index');
    }

    // --- index ---

    public function test_index_renders_costs_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('costs.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Costs/Index')
                ->has('budgets')
            );
    }

    public function test_index_forbidden_without_permission(): void
    {
        $noPermUser = User::factory()->create();

        $this->actingAs($noPermUser)
            ->get(route('costs.index'))
            ->assertForbidden();
    }

    // --- show ---

    public function test_show_displays_budget_catalog_details(): void
    {
        $budget = Budget::factory()->create();
        BudgetCatalog::factory()->create(['budget_id' => $budget->id]);

        $this->actingAs($this->user)
            ->get(route('costs.show', $budget))
            ->assertInertia(fn ($page) => $page
                ->component('Costs/Show')
                ->has('budget')
            );
    }

    // --- storeCatalog ---

    public function test_store_catalog_creates_new_version(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Catálogo']);
        $budget = Budget::factory()->create(['ticket_id' => $ticket->id]);

        $data = [
            'subtotal' => 800,
            'iva' => 128,
            'total' => 928,
            'items' => [
                [
                    'description' => 'Cable',
                    'unit' => 'metro',
                    'quantity' => 50,
                    'unit_price' => 10,
                    'total' => 500,
                ],
                [
                    'description' => 'Mano de obra',
                    'unit' => 'hora',
                    'quantity' => 8,
                    'unit_price' => 37.5,
                    'total' => 300,
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->post(route('costs.store-catalog', $budget), $data)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('budget_catalogs', [
            'budget_id' => $budget->id,
            'version' => 1,
            'total' => 928,
        ]);

        $this->assertDatabaseCount('budget_catalog_items', 2);
    }

    public function test_store_catalog_fails_validation(): void
    {
        $budget = Budget::factory()->create();

        $this->actingAs($this->user)
            ->post(route('costs.store-catalog', $budget), [])
            ->assertSessionHasErrors(['subtotal', 'iva', 'total', 'items']);
    }

    // --- print ---

    public function test_print_renders_print_view(): void
    {
        $budget = Budget::factory()->create();

        $this->actingAs($this->user)
            ->get(route('costs.print', ['budget' => $budget]))
            ->assertInertia(fn ($page) => $page
                ->component('Costs/Print')
                ->has('budget')
            );
    }
}
