<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetConcept;
use App\Models\BudgetPayment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    // --- index ---

    public function test_index_renders_budgets_page(): void
    {
        Budget::factory()->count(3)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get(route('budgets.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Budgets/Index')
                ->has('budgets.data', 3)
            );
    }

    public function test_index_shows_only_user_budgets_when_no_filters(): void
    {
        $myTicket = Ticket::factory()->create();
        Budget::factory()->create(['user_id' => $this->user->id, 'ticket_id' => $myTicket->id]);
        Budget::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->get(route('budgets.index'))
            ->assertInertia(fn ($page) => $page
                ->has('budgets.data', 1)
            );
    }

    // --- create ---

    public function test_create_renders_form(): void
    {
        Ticket::factory()->count(2)->create();

        $this->actingAs($this->user)
            ->get(route('budgets.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Budgets/Create')
                ->has('tickets', 2)
            );
    }

    // --- store ---

    public function test_store_creates_budget(): void
    {
        $ticket = Ticket::factory()->create();

        $data = [
            'ticket_id' => $ticket->id,
            'description' => 'Test budget',
            'currency' => 'MXN',
            'exchange_rate' => 1.0000,
            'user_id' => $this->user->id,
            'concepts' => [
                ['concept' => 'Material', 'amount' => 1000],
                ['concept' => 'Labor', 'amount' => 500],
            ],
        ];

        $this->actingAs($this->user)
            ->post(route('budgets.store'), $data)
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('budgets', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseCount('budget_concepts', 2);
    }

    public function test_store_fails_validation_without_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('budgets.store'), [])
            ->assertSessionHasErrors(['ticket_id', 'currency', 'exchange_rate', 'user_id']);
    }

    public function test_store_with_quick_create_returns_json(): void
    {
        $ticket = Ticket::factory()->create();

        $data = [
            'ticket_id' => $ticket->id,
            'description' => 'Quick budget',
            'currency' => 'USD',
            'exchange_rate' => 17.50,
            'user_id' => $this->user->id,
            'concepts' => [
                ['concept' => 'Test', 'amount' => 100],
            ],
            'quick_create' => true,
        ];

        $this->actingAs($this->user)
            ->post(route('budgets.store'), $data)
            ->assertStatus(201)
            ->assertJsonStructure(['budget', 'message']);
    }

    // --- show ---

    public function test_show_displays_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        BudgetConcept::factory()->count(2)->create(['budget_id' => $budget->id]);

        $this->actingAs($this->user)
            ->get(route('budgets.show', $budget))
            ->assertInertia(fn ($page) => $page
                ->component('Budgets/Show')
                ->has('budget')
            );
    }

    // --- edit ---

    public function test_edit_renders_form(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get(route('budgets.edit', $budget))
            ->assertInertia(fn ($page) => $page
                ->component('Budgets/Edit')
                ->has('budget')
            );
    }

    // --- update ---

    public function test_update_modifies_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        BudgetConcept::factory()->create(['budget_id' => $budget->id]);

        $data = [
            'ticket_id' => $budget->ticket_id,
            'description' => 'Updated description',
            'currency' => 'USD',
            'exchange_rate' => 18.00,
            'user_id' => $this->user->id,
            'concepts' => [
                ['concept' => 'New concept', 'amount' => 2000],
            ],
        ];

        $this->actingAs($this->user)
            ->put(route('budgets.update', $budget), $data)
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'description' => 'Updated description',
            'currency' => 'USD',
        ]);

        $this->assertDatabaseCount('budget_concepts', 1);
    }

    // --- destroy ---

    public function test_destroy_deletes_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->delete(route('budgets.destroy', $budget))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    // --- storePayment ---

    public function test_store_payment_creates_payment(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('budgets.payments.store', $budget), [
                'amount' => 500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Transferencia',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('budget_payments', [
            'budget_id' => $budget->id,
            'amount' => 500,
        ]);
    }

    public function test_store_payment_fails_without_required_fields(): void
    {
        $budget = Budget::factory()->create();

        $this->actingAs($this->user)
            ->post(route('budgets.payments.store', $budget), [])
            ->assertSessionHasErrors(['amount', 'payment_date', 'payment_method']);
    }

    // --- destroyPayment ---

    public function test_destroy_payment_deletes_payment(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $payment = BudgetPayment::factory()->create(['budget_id' => $budget->id]);

        $this->actingAs($this->user)
            ->delete(route('budgets.payments.destroy', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('budget_payments', ['id' => $payment->id]);
    }

    // --- storeFile ---

    public function test_store_file_attaches_files(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('budgets.files.store', $budget), [])
            ->assertSessionHasErrors(['files']);
    }

    // --- storeTechnicianPayment ---

    public function test_store_technician_payment_creates_record(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('budgets.technician-payments.store', $budget), [])
            ->assertSessionHasErrors(['user_id', 'amount', 'payment_date', 'payment_method', 'proof']);
    }

    // --- destroyTechnicianPayment ---

    public function test_destroy_technician_payment_deletes_record(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $payment = \App\Models\TechnicianPayment::factory()->create([
            'budget_id' => $budget->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('budgets.technician-payments.destroy', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('technician_payments', ['id' => $payment->id]);
    }
}
