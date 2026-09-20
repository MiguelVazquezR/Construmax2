<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayslipService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayslipController extends Controller
{
    public function __construct(
        private readonly PayslipService $payslipService,
    ) {}

    /**
     * Printable payslips of a period. Closed periods print the frozen
     * payslips; open periods print the live pre-payroll, so receipts no
     * longer require closing the period first. Accepts an optional "users"
     * query parameter with the collaborator ids to print. Collaborators
     * without the management permission can only print their own payslip.
     */
    public function print(Request $request, PayrollPeriod $period): Response
    {
        $actor = $request->user();
        $canViewAll = $actor->can('payroll.payslips.view');

        $requestedUsers = $request->filled('users')
            ? collect(is_array($request->input('users')) ? $request->input('users') : explode(',', (string) $request->input('users')))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
            : collect();

        if (! $canViewAll && ($requestedUsers->isEmpty() || $requestedUsers->diff([$actor->id])->isNotEmpty())) {
            abort(403);
        }

        $payslips = $this->payslipService->printPayloads($period, $requestedUsers->all());

        return Inertia::render('Payroll/Payslips/Print', [
            'period' => [
                'id' => $period->id,
                'label' => $period->label(),
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $period->status,
            ],
            'payslips' => $payslips,
            'isPreview' => $period->isOpen(),
            'appName' => config('app.name'),
        ]);
    }
}
