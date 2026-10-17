<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Human Resources module suspension
    |--------------------------------------------------------------------------
    |
    | The payroll and Human Resources module stays blocked while the balance
    | of its development is pending payment. Every screen inside the
    | "Recursos Humanos" sidebar menu is redirected to the suspension notice.
    | Set PAYROLL_SUSPENSION_ACTIVE to false once the balance is settled to
    | restore the module without touching any code.
    |
    */

    'suspension' => [
        'active' => (bool) env('PAYROLL_SUSPENSION_ACTIVE', true),

        // Date from which the balance is pending (shown in the notice).
        'pending_since' => env('PAYROLL_SUSPENSION_SINCE', '2026-09-28'),

        // Optional amount pending to settle. When empty, the notice only
        // mentions that a balance is pending.
        'amount' => env('PAYROLL_SUSPENSION_AMOUNT'),
        'currency' => env('PAYROLL_SUSPENSION_CURRENCY', 'MXN'),

        // Optional contact data rendered as the support action.
        'contact_email' => env('PAYROLL_SUSPENSION_CONTACT_EMAIL'),
        'contact_phone' => env('PAYROLL_SUSPENSION_CONTACT_PHONE'),
    ],

];
