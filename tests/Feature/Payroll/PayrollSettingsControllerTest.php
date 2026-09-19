<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create([
            'name' => 'payroll.settings.manage',
            'guard_name' => 'web',
            'category' => 'Nómina',
            'description' => 'Manage payroll settings',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.settings.manage');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'face_recognition_enabled' => false,
            'face_match_threshold' => 90,
            'kiosk_pin_fallback_enabled' => true,
            'rekognition_collection_id' => 'construmax-attendance',
            'period_type' => 'weekly',
            'period_anchor_date' => '2026-09-21',
            'late_tolerance_minutes' => 10,
            'late_discount_mode' => 'track_only',
            'overtime_double_multiplier' => 2,
            'overtime_triple_multiplier' => 3,
            'overtime_weekly_threshold_hours' => 9,
            'holiday_worked_extra_multiplier' => 2,
            'vacation_min_days_to_request' => 1,
            'vacation_carryover_months' => 18,
            'incapacity_paid' => false,
            'incapacity_pay_percentage' => 60,
            'default_daily_hours' => 8,
            'payroll_expense_category_id' => null,
            'attendance_capture_retention_months' => 12,
            'remote_geolocation_required' => true,
        ], $overrides);
    }

    public function test_settings_page_renders_the_singleton_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payroll.settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Settings/Index')
                ->has('settings')
                ->has('periodTypes')
                ->has('lateDiscountModes')
                ->has('expenseCategories')
            );
    }

    public function test_settings_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('payroll.settings.edit'))
            ->assertForbidden();
    }

    public function test_update_saves_the_configuration(): void
    {
        $this->actingAs($this->admin)
            ->put(route('payroll.settings.update'), $this->validPayload([
                'period_type' => 'semimonthly',
                'late_tolerance_minutes' => 15,
                'late_discount_mode' => 'deduct_minutes',
                'incapacity_paid' => true,
                'incapacity_pay_percentage' => 70,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = PayrollSetting::current()->refresh();

        $this->assertSame('semimonthly', $settings->period_type);
        $this->assertEquals(15, $settings->late_tolerance_minutes);
        $this->assertSame('deduct_minutes', $settings->late_discount_mode);
        $this->assertTrue($settings->incapacity_paid);
        $this->assertEquals(70, $settings->incapacity_pay_percentage);
        $this->assertEquals($this->admin->id, $settings->updated_by);
    }

    public function test_update_validates_the_face_match_threshold(): void
    {
        $this->actingAs($this->admin)
            ->put(route('payroll.settings.update'), $this->validPayload(['face_match_threshold' => 10]))
            ->assertSessionHasErrors('face_match_threshold');
    }

    public function test_update_validates_the_triple_multiplier_against_the_double_one(): void
    {
        $this->actingAs($this->admin)
            ->put(route('payroll.settings.update'), $this->validPayload([
                'overtime_double_multiplier' => 3,
                'overtime_triple_multiplier' => 2,
            ]))
            ->assertSessionHasErrors('overtime_triple_multiplier');
    }

    public function test_update_requires_the_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->put(route('payroll.settings.update'), $this->validPayload())
            ->assertForbidden();
    }
}
