<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PayrollSuspensionController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Payroll/Suspended', [
            'pendingSince' => config('payroll.suspension.pending_since'),
            'amount' => config('payroll.suspension.amount'),
            'currency' => config('payroll.suspension.currency'),
            'contactEmail' => config('payroll.suspension.contact_email'),
            'contactPhone' => config('payroll.suspension.contact_phone'),
        ]);
    }
}
