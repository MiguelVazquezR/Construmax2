<?php

namespace Tests\Feature;

use App\Models\PayrollProfile;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    // --- index ---

    public function test_index_renders_technicians_page(): void
    {
        User::factory()->count(2)->asTechnician()->create();

        $this->actingAs($this->user)
            ->get(route('technicians.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Technicians/Index')
                ->has('technicians.data', 2)
            );
    }

    // --- create ---

    public function test_create_renders_form(): void
    {
        $this->actingAs($this->user)
            ->get(route('technicians.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Technicians/Create')
                ->has('availableSpecialties')
            );
    }

    // --- store ---

    public function test_store_creates_technician_with_user(): void
    {
        $data = [
            'name' => 'John Technician',
            'email' => 'john.tech@test.com',
            'phone' => '8112345678',
            'is_internal' => false,
            'state' => 'Nuevo León',
            'city' => 'Monterrey',
            'specialties' => ['Electricidad baja tensión', 'Plomería / Fontanería'],
        ];

        $this->actingAs($this->user)
            ->post(route('technicians.store'), $data)
            ->assertRedirect(route('technicians.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'john.tech@test.com']);
        $this->assertDatabaseHas('technicians', ['phone' => '8112345678']);
    }

    public function test_store_requires_name_and_phone(): void
    {
        $this->actingAs($this->user)
            ->post(route('technicians.store'), [])
            ->assertSessionHasErrors(['name', 'phone']);
    }

    // --- show ---

    public function test_show_displays_technician(): void
    {
        $techUser = User::factory()->asTechnician()->create();

        $this->actingAs($this->user)
            ->get(route('technicians.show', $techUser->technician))
            ->assertInertia(fn ($page) => $page
                ->component('Technicians/Show')
                ->has('technician')
                ->has('kpis')
            );
    }

    // --- edit ---

    public function test_edit_renders_form(): void
    {
        $techUser = User::factory()->asTechnician()->create();

        $this->actingAs($this->user)
            ->get(route('technicians.edit', $techUser->technician))
            ->assertInertia(fn ($page) => $page
                ->component('Technicians/Edit')
                ->has('technician')
            );
    }

    // --- update ---

    public function test_update_modifies_technician(): void
    {
        $techUser = User::factory()->asTechnician()->create();
        $technician = $techUser->technician;

        $this->actingAs($this->user)
            ->put(route('technicians.update', $technician), [
                'name' => 'Updated Tech',
                'phone' => '8198765432',
                'status' => 'Activo',
            ])
            ->assertRedirect(route('technicians.show', $technician->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $techUser->id,
            'name' => 'Updated Tech',
        ]);
        $this->assertDatabaseHas('technicians', [
            'id' => $technician->id,
            'phone' => '8198765432',
        ]);
    }

    // --- updateStatus ---

    public function test_update_status_changes_technician_status(): void
    {
        $techUser = User::factory()->asTechnician(['status' => 'Activo'])->create();
        $technician = $techUser->technician;

        $this->actingAs($this->user)
            ->put(route('technicians.update-status', $technician), ['status' => 'Inactivo'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('technicians', [
            'id' => $technician->id,
            'status' => 'Inactivo',
        ]);
    }

    public function test_update_status_requires_valid_status(): void
    {
        $techUser = User::factory()->asTechnician()->create();
        $technician = $techUser->technician;

        $this->actingAs($this->user)
            ->put(route('technicians.update-status', $technician), ['status' => 'Invalid'])
            ->assertSessionHasErrors('status');
    }

    // --- updateRating ---

    public function test_update_rating_sets_rating_avg(): void
    {
        $techUser = User::factory()->asTechnician(['rating_avg' => 3.0])->create();
        $technician = $techUser->technician;

        $this->actingAs($this->user)
            ->put(route('technicians.update-rating', $technician), ['rating' => 4.5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('technicians', [
            'id' => $technician->id,
            'rating_avg' => 4.5,
        ]);
    }

    // --- quickStore ---

    public function test_quick_store_creates_technician_and_returns_json(): void
    {
        $data = [
            'name' => 'Quick Tech',
            'phone' => '8111111111',
            'is_internal' => true,
        ];

        $this->actingAs($this->user)
            ->post(route('technicians.quick-store'), $data)
            ->assertStatus(201)
            ->assertJsonStructure(['user', 'message']);

        $this->assertDatabaseHas('users', ['name' => 'Quick Tech']);
        $this->assertDatabaseHas('technicians', ['phone' => '8111111111']);
    }

    public function test_quick_store_requires_name_and_phone(): void
    {
        $this->actingAs($this->user)
            ->post(route('technicians.quick-store'), [])
            ->assertSessionHasErrors(['name', 'phone']);
    }

    // --- destroy ---

    public function test_destroy_soft_deletes_an_external_technician(): void
    {
        $techUser = User::factory()->externalTechnician()->create();
        $technician = $techUser->technician;

        $this->actingAs($this->user)
            ->delete(route('technicians.destroy', $technician))
            ->assertRedirect(route('technicians.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $techUser->id]);
        $this->assertDatabaseHas('technicians', ['id' => $technician->id, 'status' => 'Eliminado']);
    }

    public function test_dismissing_an_internal_technician_saves_the_termination_date(): void
    {
        $techUser = User::factory()->internalTechnician()->create();
        $technician = $techUser->technician;
        $profile = PayrollProfile::create([
            'user_id' => $techUser->id,
            'hire_date' => '2026-01-15',
            'is_payroll_subject' => true,
        ]);

        $this->actingAs($this->user)
            ->delete(route('technicians.destroy', $technician), ['termination_date' => '2026-09-20'])
            ->assertRedirect(route('technicians.index'))
            ->assertSessionHas('success');

        // The technician stays in the system (reactivatable) but deactivated.
        $this->assertFalse((bool) $techUser->fresh()->is_active);
        $this->assertFalse($techUser->fresh()->trashed());
        $this->assertSame('Inactivo', $technician->fresh()->status);
        $this->assertSame('2026-09-20', $profile->fresh()->termination_date->toDateString());

        // Still in the payroll period that contains the date, gone afterwards.
        $this->assertTrue(PayrollProfile::payrollSubjectOn('2026-09-16')->whereKey($profile->id)->exists());
        $this->assertFalse(PayrollProfile::payrollSubjectOn('2026-10-01')->whereKey($profile->id)->exists());
    }

    public function test_dismissing_an_internal_technician_rejects_a_date_before_the_hire_date(): void
    {
        $techUser = User::factory()->asTechnician(['is_internal' => true, 'status' => 'Activo'])->create();
        $technician = $techUser->technician;
        $profile = PayrollProfile::create([
            'user_id' => $techUser->id,
            'hire_date' => '2026-01-15',
            'is_payroll_subject' => true,
        ]);

        $this->actingAs($this->user)
            ->delete(route('technicians.destroy', $technician), ['termination_date' => '2025-12-31'])
            ->assertSessionHasErrors('termination_date');

        $this->assertTrue((bool) $techUser->fresh()->is_active);
        $this->assertSame('Activo', $technician->fresh()->status);
        $this->assertNull($profile->fresh()->termination_date);
    }

    public function test_dismissing_an_internal_technician_creates_the_payroll_profile_with_the_date(): void
    {
        $techUser = User::factory()->internalTechnician()->create();
        $technician = $techUser->technician;

        $this->actingAs($this->user)
            ->delete(route('technicians.destroy', $technician), ['termination_date' => '2026-09-20'])
            ->assertRedirect(route('technicians.index'));

        $profile = PayrollProfile::where('user_id', $techUser->id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('2026-09-20', $profile->termination_date->toDateString());
    }

    public function test_reactivating_a_dismissed_internal_technician_clears_the_termination_date(): void
    {
        $techUser = User::factory()->internalTechnician()->create();
        $technician = $techUser->technician;
        $profile = PayrollProfile::create([
            'user_id' => $techUser->id,
            'hire_date' => '2026-01-15',
            'is_payroll_subject' => true,
            'termination_date' => '2026-09-20',
        ]);
        $techUser->update(['is_active' => false]);
        $technician->update(['status' => 'Inactivo']);

        $this->actingAs($this->user)
            ->patch(route('technicians.restore', $technician->id))
            ->assertRedirect(route('technicians.index'))
            ->assertSessionHas('success');

        $this->assertTrue((bool) $techUser->fresh()->is_active);
        $this->assertNull($profile->fresh()->termination_date);
        $this->assertSame('Activo', $technician->fresh()->status);
    }
}
