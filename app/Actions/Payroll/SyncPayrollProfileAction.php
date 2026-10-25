<?php

namespace App\Actions\Payroll;

use App\Models\PayrollProfile;
use App\Models\User;

class SyncPayrollProfileAction
{
    /**
     * Fields that require the payroll.profiles.manage permission.
     *
     * @var array<int, string>
     */
    private const MANAGE_FIELDS = [
        'employee_number',
        'hire_date',
        'termination_date',
        'daily_salary',
        'daily_hours',
        'is_payroll_subject',
        'is_attendance_subject',
    ];

    /**
     * Keep only the payroll attributes the acting user is allowed to change.
     * The remote attendance flag can also be changed with its own permission.
     */
    public function sanitizeFor(User $actor, array $data): array
    {
        $canManage = $actor->can('payroll.profiles.manage');
        $canManageRemote = $actor->can('payroll.remote-attendance.manage');

        $allowed = [];

        if ($canManage) {
            foreach (self::MANAGE_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $allowed[$field] = $data[$field];
                }
            }
        }

        if (($canManage || $canManageRemote) && array_key_exists('can_remote_attendance', $data)) {
            $allowed['can_remote_attendance'] = (bool) $data['can_remote_attendance'];
        }

        return $allowed;
    }

    /**
     * Create or update the payroll profile of a user. Keys absent from the
     * attributes are left untouched.
     */
    public function execute(User $user, array $attributes): ?PayrollProfile
    {
        if ($attributes === []) {
            return null;
        }

        $profile = PayrollProfile::firstOrNew(['user_id' => $user->id]);

        foreach ($attributes as $key => $value) {
            $profile->{$key} = $value;
        }

        // Without attendance there is no remote attendance.
        if (array_key_exists('is_attendance_subject', $attributes) && ! $attributes['is_attendance_subject']) {
            $profile->can_remote_attendance = false;
        }

        // Registering attendance requires being a payroll subject: without
        // payroll there is no period where those records could be reviewed.
        if ($profile->is_attendance_subject) {
            $profile->is_payroll_subject = true;
        }

        $profile->save();

        return $profile;
    }
}
