<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayslipController extends Controller
{
    /**
     * Printable payslips of a closed period. Accepts an optional "users"
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

        $payslips = $period->payslips()
            ->with(['user:id,name', 'lines'])
            ->when($requestedUsers->isNotEmpty(), fn ($query) => $query->whereIn('user_id', $requestedUsers->all()))
            ->get()
            ->map(fn ($payslip) => [
                'id' => $payslip->id,
                'user_name' => $payslip->user?->name,
                'employee_number' => $payslip->employee_number,
                'department' => $payslip->department,
                'position' => $payslip->position,
                'days_worked' => (float) $payslip->days_worked,
                'days_paid' => (float) $payslip->days_paid,
                'unpaid_days' => (float) $payslip->unpaid_days,
                'late_minutes' => $payslip->late_minutes,
                'overtime_minutes' => $payslip->overtime_double_minutes + $payslip->overtime_triple_minutes,
                'vacation_days' => (float) $payslip->vacation_days,
                'incapacity_days' => (float) $payslip->incapacity_days,
                'daily_salary' => (float) $payslip->daily_salary,
                'total_gross' => (float) $payslip->total_gross,
                'total_deductions' => (float) $payslip->total_deductions,
                'total_net' => (float) $payslip->total_net,
                'lines' => $payslip->lines->map(fn ($line) => [
                    'concept' => $line->concept,
                    'type' => $line->type,
                    'quantity' => $line->quantity !== null ? (float) $line->quantity : null,
                    'amount' => (float) $line->amount,
                ])->values(),
            ]);

        return Inertia::render('Payroll/Payslips/Print', [
            'period' => [
                'id' => $period->id,
                'label' => $period->label(),
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $period->status,
            ],
            'payslips' => $payslips,
            'appName' => config('app.name'),
        ]);
    }
}
