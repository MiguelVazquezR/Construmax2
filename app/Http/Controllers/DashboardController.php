<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\Calendar;
use App\Models\Ticket;
use App\Models\Budget;
use App\Models\Customer;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::now();

        // 1. Datos personales
        $myEventsToday = Calendar::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($sub) use ($user) {
                      $sub->where('user_id', $user->id)->where('status', 'Aceptado');
                  });
            })
            ->whereDate('start_time', $today->toDateString())
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        $myPendingTickets = Ticket::whereHas('tasks', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereNotIn('status', ['Completado', 'Cancelado'])
            ->with(['customer'])
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_start', 'asc')
            ->limit(5)
            ->get();

        // 2. Datos Gerenciales
        $kpis = [];
        
        if ($user->can('crm.analytics')) {
            // Obtenemos los pagos del mes actual
            // Hacemos JOIN para saber la moneda original del presupuesto
            $monthlyPayments = DB::table('budget_payments')
                ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
                ->whereMonth('budget_payments.payment_date', $today->month)
                ->whereYear('budget_payments.payment_date', $today->year)
                ->select(
                    'budget_payments.amount', 
                    'budgets.currency'
                )
                ->get();

            $totalMXN = 0;
            $totalUSD = 0;

            foreach ($monthlyPayments as $payment) {
                // Si la moneda no está definida, asumimos MXN
                $currency = $payment->currency ?? 'MXN';

                if ($currency === 'USD') {
                    // Solo suma a la bolsa de Dólares
                    $totalUSD += $payment->amount;
                } else {
                    // Solo suma a la bolsa de Pesos
                    $totalMXN += $payment->amount;
                }
            }

            $kpis['crm'] = [
                'customers_month' => Customer::whereMonth('created_at', $today->month)->count(),
                'budgets_pending' => Budget::whereHas('ticket', fn($q) => $q->where('status', 'En proceso'))->count(),
                // Enviamos los totales separados (sin conversión)
                'sales_month_mxn' => $totalMXN,
                'sales_month_usd' => $totalUSD,
            ];
        }

        if ($user->can('tickets.analytics')) {
            $kpis['ops'] = [
                'active_tickets' => Ticket::where('status', 'En proceso')->count(),
                'overdue_tickets' => Ticket::where('status', '!=', 'Completado')
                    ->where('scheduled_end', '<', $today)->count(),
            ];
        }

        // Módulo de Costos: presupuestos con ticket en Catálogo
        $costsBudgets = Budget::whereHas('ticket', fn($q) => $q->where('status', 'Catálogo'))
            ->with(['ticket.customer'])
            ->latest()
            ->limit(5)
            ->get();

        $kpis['costs'] = [
            'total' => Budget::whereHas('ticket', fn($q) => $q->where('status', 'Catálogo'))->count(),
            'budgets' => $costsBudgets,
        ];

        // Módulo de Facturación: presupuestos con ticket en Ejecutado
        $invoicingBudgets = Budget::whereHas('ticket', fn($q) => $q->where('status', 'Ejecutado'))
            ->with(['ticket.customer'])
            ->latest()
            ->limit(5)
            ->get();

        $kpis['invoicing'] = [
            'total' => Budget::whereHas('ticket', fn($q) => $q->where('status', 'Ejecutado'))->count(),
            'budgets' => $invoicingBudgets,
        ];

        return Inertia::render('Dashboard', [
            'my_day' => [
                'events' => $myEventsToday,
                'tickets' => $myPendingTickets,
            ],
            'kpis' => $kpis,
        ]);
    }
}