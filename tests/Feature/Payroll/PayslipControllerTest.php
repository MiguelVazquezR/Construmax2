<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayslipControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.payslips.view', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'View payslips']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.payslips.view');

        $this->employee = User::factory()->create(['is_active' => true, 'name' => 'Empleado Recibo']);

        $this->period = PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-13',
            'status' => PayrollPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'total_gross' => 2000,
            'total_deductions' => 0,
            'total_net' => 2000,
        ]);

        $payslip = Payslip::create([
            'payroll_period_id' => $this->period->id,
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-0001',
            'daily_salary' => 400,
            'days_paid' => 5,
            'total_gross' => 2000,
            'total_deductions' => 0,
            'total_net' => 2000,
            'generated_at' => now(),
        ]);

        $payslip->lines()->create([
            'concept' => 'Sueldo',
            'type' => 'earning',
            'quantity' => 5,
            'unit_rate' => 400,
            'amount' => 2000,
            'source' => 'attendance',
            'sort_order' => 10,
        ]);
    }

    public function test_print_renders_the_payslips_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payroll.periods.payslips.print', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Payslips/Print')
                ->has('payslips', 1)
                ->where('payslips.0.user_name', 'Empleado Recibo')
                ->where('payslips.0.lines.0.concept', 'Sueldo')
            );
    }

    public function test_print_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->employee)
            ->get(route('payroll.periods.payslips.print', $this->period))
            ->assertForbidden();
    }

    public function test_print_filters_by_users(): void
    {
        $other = User::factory()->create(['is_active' => true, 'name' => 'Otro Empleado']);

        Payslip::create([
            'payroll_period_id' => $this->period->id,
            'user_id' => $other->id,
            'daily_salary' => 300,
            'days_paid' => 5,
            'total_gross' => 1500,
            'total_deductions' => 0,
            'total_net' => 1500,
            'generated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.payslips.print', [
                'period' => $this->period->id,
                'users' => [$this->employee->id],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payslips', 1)
                ->where('payslips.0.user_name', 'Empleado Recibo')
            );
    }

    public function test_print_shows_an_empty_list_for_a_period_without_payslips(): void
    {
        $emptyPeriod = PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-08-31',
            'end_date' => '2026-09-06',
            'status' => PayrollPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.payslips.print', $emptyPeriod))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('payslips', 0));
    }
}
