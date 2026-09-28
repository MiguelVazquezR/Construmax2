<?php

namespace App\Actions\Payroll;

use App\Models\PayrollSetting;
use App\Models\User;

class UpdatePayrollSettingsAction
{
    public function execute(array $data, User $editor): PayrollSetting
    {
        $settings = PayrollSetting::current();

        $settings->fill($data);
        $settings->updated_by = $editor->id;
        $settings->save();

        return $settings;
    }
}
