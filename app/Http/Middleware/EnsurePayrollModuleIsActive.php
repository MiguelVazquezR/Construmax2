<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block every Human Resources screen while the payroll development balance is
 * pending payment. All blocked visits are redirected to the suspension notice,
 * so no payroll screen or endpoint can be reached.
 */
class EnsurePayrollModuleIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // The suspension notice itself must always stay reachable, otherwise
        // the redirect would loop back into the middleware.
        if (! config('payroll.suspension.active') || $request->routeIs('payroll.suspended')) {
            return $next($request);
        }

        return redirect()->route('payroll.suspended');
    }
}
