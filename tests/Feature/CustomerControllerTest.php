<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    // --- index ---

    public function test_index_renders_customers_page(): void
    {
        Customer::factory()->count(5)->create();

        $this->actingAs($this->user)
            ->get(route('customers.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Index')
                ->has('customers.data', 5)
            );
    }

    public function test_index_filters_by_search(): void
    {
        Customer::factory()->create(['name' => 'Alpha Corp']);
        Customer::factory()->create(['name' => 'Beta Corp']);

        $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'Alpha']))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
            );
    }

    // --- create ---

    public function test_create_renders_form(): void
    {
        $this->actingAs($this->user)
            ->get(route('customers.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Create')
            );
    }

    // --- store ---

    public function test_store_creates_customer_with_branches_and_contacts(): void
    {
        $data = [
            'name' => 'Test Customer',
            'business_name' => 'Test Customer SA de CV',
            'rfc' => 'TST010101ABC',
            'payment_condition' => 'Contado',
            'payment_method' => 'Transferencia',
            'invoice_usage' => 'Gastos generales',
            'currency' => 'MXN',
            'branches' => [
                ['country' => 'México', 'region' => 'Nuevo León', 'unit' => 'Oficina', 'branch_name' => 'Sucursal Monterrey'],
            ],
            'contacts' => [
                [
                    'name' => 'John Doe',
                    'email' => 'john@test.com',
                    'phone' => '8112345678',
                    'position' => 'Gerente',
                    'branch_indices' => [0],
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->post(route('customers.store'), $data)
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', ['name' => 'Test Customer']);
        $this->assertDatabaseHas('customer_branches', ['branch_name' => 'Sucursal Monterrey']);
        $this->assertDatabaseHas('customer_contacts', ['name' => 'John Doe']);
    }

    public function test_store_fails_validation_without_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('customers.store'), [])
            ->assertSessionHasErrors([
                'name', 'business_name', 'rfc', 'payment_condition',
                'payment_method', 'invoice_usage', 'currency',
                'branches', 'contacts',
            ]);
    }

    public function test_store_requires_unique_rfc(): void
    {
        Customer::factory()->create(['rfc' => 'DUP010101ABC']);

        $this->actingAs($this->user)
            ->post(route('customers.store'), [
                'rfc' => 'DUP010101ABC',
            ])
            ->assertSessionHasErrors('rfc');
    }

    // --- show ---

    public function test_show_displays_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->user)
            ->get(route('customers.show', $customer))
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Show')
                ->has('customer')
            );
    }

    // --- edit ---

    public function test_edit_renders_form(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->user)
            ->get(route('customers.edit', $customer))
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Edit')
                ->has('customer')
            );
    }

    // --- update ---

    public function test_update_modifies_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $data = [
            'name' => 'New Name',
            'business_name' => 'New Name SA de CV',
            'rfc' => $customer->rfc,
            'payment_condition' => 'Crédito 30 días',
            'payment_method' => 'Cheque',
            'invoice_usage' => 'Gastos de operación',
            'currency' => 'USD',
            'branches' => [
                ['country' => 'México', 'region' => 'CDMX', 'unit' => 'Oficina', 'branch_name' => 'Principal'],
            ],
            'contacts' => [
                [
                    'name' => 'Jane Doe',
                    'email' => 'jane@test.com',
                    'phone' => '5512345678',
                    'position' => 'Director',
                    'branch_indices' => [0],
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->put(route('customers.update', $customer), $data)
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Name',
        ]);
    }

    // --- toggleStatus ---

    public function test_toggle_status_deactivates_and_reactivates_customer(): void
    {
        $customer = Customer::factory()->create(['is_active' => true]);

        // Deactivate
        $this->actingAs($this->user)
            ->put(route('customers.toggle-status', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
        ]);

        // Reactivate
        $this->actingAs($this->user)
            ->put(route('customers.toggle-status', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => true,
        ]);
    }

    // --- destroy ---

    public function test_destroy_deletes_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
