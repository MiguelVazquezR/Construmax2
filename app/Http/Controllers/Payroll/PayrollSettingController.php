<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\UpdatePayrollSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\UpdatePayrollSettingsRequest;
use App\Models\ExpenseCategory;
use App\Models\PayrollSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollSettingController extends Controller
{
    public function __construct(
        private readonly UpdatePayrollSettingsAction $updateSettingsAction,
    ) {}

    public function edit(Request $request): Response
    {
        if (! $request->user()->can('payroll.settings.manage')) {
            abort(403);
        }

        return Inertia::render('Payroll/Settings/Index', [
            'settings' => PayrollSetting::current(),
            'periodTypes' => PayrollSetting::PERIOD_TYPES,
            'lateDiscountModes' => PayrollSetting::LATE_DISCOUNT_MODES,
            'expenseCategories' => ExpenseCategory::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePayrollSettingsRequest $request): RedirectResponse
    {
        $this->updateSettingsAction->execute($request->validated(), $request->user());

        return back()->with('success', 'Configuración de nómina actualizada.');
    }
}
