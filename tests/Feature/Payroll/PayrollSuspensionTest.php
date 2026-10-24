<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests run with the module enabled (phpunit.xml); these cases turn
        // the suspension on to verify the guard.
        config(['payroll.suspension.active' => true]);

        Permission::create([
            'name' => 'payroll.settings.manage',
            'guard_name' => 'web',
            'category' => 'Nómina',
            'description' => 'Manage payroll settings',
        ]);

        Permission::create([
            'name' => 'payroll.periods.index',
            'guard_name' => 'web',
            'category' => 'Nómina',
            'description' => 'View payroll periods',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo(['payroll.settings.manage', 'payroll.periods.index']);
    }

    public function test_every_payroll_screen_redirects_to_the_suspension_notice(): void
    {
        $this->actingAs($this->admin);

        $screens = [
            route('payroll.settings.edit'),
            route('payroll.periods.index'),
            route('payroll.vacations.index'),
            route('payroll.shifts.index'),
            route('payroll.holidays.index'),
            route('payroll.devices.index'),
            route('payroll.my-attendance.index'),
        ];

        foreach ($screens as $url) {
            $this->get($url)->assertRedirect(route('payroll.suspended'));
        }
    }

    public function test_write_requests_are_redirected_to_the_suspension_notice(): void
    {
        $this->actingAs($this->admin)
            ->put(route('payroll.settings.update'), [])
            ->assertRedirect(route('payroll.suspended'));
    }

    public function test_the_suspension_notice_explains_the_pending_balance(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payroll.suspended'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Suspended')
                ->where('pendingSince', '2026-09-28')
                ->where('currency', 'MXN')
            );
    }

    public function test_the_payroll_module_works_when_the_suspension_is_disabled(): void
    {
        config(['payroll.suspension.active' => false]);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.index'))
            ->assertOk();
    }

    public function test_guests_cannot_reach_the_suspension_notice(): void
    {
        $this->get(route('payroll.suspended'))->assertRedirect(route('login'));
    }
}
