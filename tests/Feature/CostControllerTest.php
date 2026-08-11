<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetCatalog;
use App\Models\BudgetCatalogItem;
use App\Models\Customer;
use App\Models\CustomerBranch;
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

    public function test_index_search_by_ticket_folio(): void
    {
        $customer = Customer::factory()->create();

        $branchA = CustomerBranch::factory()->create([
            'customer_id' => $customer->id,
            'region'  => 'Querétaro',
            'country' => 'México',
        ]);

        $branchB = CustomerBranch::factory()->create([
            'customer_id' => $customer->id,
            'region'  => 'Texas',
            'country' => 'Estados Unidos',
        ]);

        $ticketA = Ticket::factory()->create([
            'customer_branch_id' => $branchA->id,
            'name'               => 'Proyecto Alpha',
        ]);

        $ticketB = Ticket::factory()->create([
            'customer_branch_id' => $branchB->id,
            'name'               => 'Proyecto Beta',
        ]);

        $budgetA = Budget::factory()->create(['ticket_id' => $ticketA->id]);
        $budgetB = Budget::factory()->create(['ticket_id' => $ticketB->id]);

        // Search by folio number (the ticket id portion of the folio)
        $this->actingAs($this->user)
            ->get(route('costs.index', ['search' => (string) $ticketA->id, 'catalog' => 'all']))
            ->assertInertia(fn ($page) => $page
                ->component('Costs/Index')
                ->has('budgets.data', 1)
                ->where('budgets.data.0.id', $budgetA->id)
            );

        // Search by folio region code (the region portion of the folio, e.g. QUE)
        $this->actingAs($this->user)
            ->get(route('costs.index', ['search' => 'QUER', 'catalog' => 'all']))
            ->assertInertia(fn ($page) => $page
                ->component('Costs/Index')
                ->has('budgets.data', 1)
                ->where('budgets.data.0.id', $budgetA->id)
            );
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
