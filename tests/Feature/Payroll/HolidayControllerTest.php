<?php

namespace Tests\Feature\Payroll;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HolidayControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.holidays.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage holidays']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.holidays.manage');
    }

    public function test_index_renders_and_generates_the_current_year(): void
    {
        $currentYear = (int) now()->year;

        $this->actingAs($this->admin)
            ->get(route('payroll.holidays.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Holidays/Index')
                ->has('holidays')
                ->has('years')
            );

        $this->assertSame(7, Holiday::query()->forYear($currentYear)->count());
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('payroll.holidays.index'))
            ->assertForbidden();
    }

    public function test_store_registers_a_manual_holiday(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.holidays.store'), [
                'date' => '2026-11-02',
                'name' => 'Aniversario de la empresa',
                'is_mandatory' => false,
                'apply_extra_pay' => false,
                'notes' => 'Descanso interno',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('holidays', [
            'name' => 'Aniversario de la empresa',
            'year' => 2026,
            'source' => Holiday::SOURCE_MANUAL,
        ]);
    }

    public function test_store_rejects_a_duplicated_date(): void
    {
        Holiday::create([
            'date' => '2026-11-02',
            'name' => 'Día existente',
            'year' => 2026,
        ]);

        $this->actingAs($this->admin)
            ->post(route('payroll.holidays.store'), [
                'date' => '2026-11-02',
                'name' => 'Otro día',
            ])
            ->assertSessionHasErrors('date');
    }

    public function test_sync_generates_the_official_days_of_a_year(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.holidays.sync'), ['year' => 2027])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(7, Holiday::query()->forYear(2027)->count());
    }

    public function test_destroy_deletes_a_holiday(): void
    {
        $holiday = Holiday::create([
            'date' => '2026-12-24',
            'name' => 'Nochebuena (empresa)',
            'year' => 2026,
            'source' => Holiday::SOURCE_MANUAL,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('payroll.holidays.destroy', $holiday))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_actions_require_the_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('payroll.holidays.store'), ['date' => '2026-11-02', 'name' => 'X'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('payroll.holidays.sync'), ['year' => 2027])
            ->assertForbidden();
    }
}
