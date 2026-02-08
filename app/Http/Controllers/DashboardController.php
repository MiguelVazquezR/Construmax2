<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Agregado para usar DB facade si es necesario
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\Calendar;
use App\Models\Ticket;
use App\Models\Budget;
use App\Models\Customer;
// Asegúrate de importar el modelo de pagos si existe, o usar DB table
use App\Models\BudgetPayment; 

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::now();

        // 1. Datos personales (Para todos los usuarios)
        // Eventos de hoy
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

        // Tickets asignados pendientes
        $myPendingTickets = Ticket::where('user_id', $user->id)
            ->whereNotIn('status', ['Completado', 'Cancelado'])
            ->with(['budget.customer'])
            ->orderBy('priority', 'desc') // Urgentes primero
            ->orderBy('scheduled_start', 'asc')
            ->limit(5)
            ->get();

        // 2. Datos Gerenciales (Solo si tiene permisos)
        $kpis = [];
        
        // Si puede ver analíticas CRM (Ventas)
        if ($user->can('crm.analytics')) {
            // Obtenemos los pagos del mes para calcular totales multi-moneda
            // Asumimos que la tabla budget_payments tiene columnas 'amount', 'currency' ('MXN', 'USD') y 'exchange_rate'
            $monthlyPayments = DB::table('budget_payments')
                ->whereMonth('payment_date', $today->month)
                ->whereYear('payment_date', $today->year) // Importante filtrar año también
                ->get();

            $totalMXN = 0;
            $totalUSD = 0;

            foreach ($monthlyPayments as $payment) {
                $amount = $payment->amount;
                // Si no hay tipo de cambio registrado, asumimos 1 (evitar error división)
                // Ojo: Deberías tener validación al guardar el pago para que exchange_rate nunca sea 0
                $rate = $payment->exchange_rate > 0 ? $payment->exchange_rate : 1; 

                if ($payment->currency === 'USD') {
                    // El pago fue en Dólares
                    $totalUSD += $amount;           // Suma directa a bolsa USD
                    $totalMXN += $amount * $rate;   // Conversión a pesos
                } else {
                    // El pago fue en Pesos (MXN)
                    $totalMXN += $amount;           // Suma directa a bolsa MXN
                    $totalUSD += $amount / $rate;   // Conversión aproximada a dólares
                }
            }

            $kpis['crm'] = [
                'customers_month' => Customer::whereMonth('created_at', $today->month)->count(),
                'budgets_pending' => Budget::whereIn('status', ['Presupuesto enviado', 'Trabajo en proceso'])->count(),
                // Enviamos ambos totales a la vista
                'sales_month_mxn' => $totalMXN,
                'sales_month_usd' => $totalUSD,
            ];
        }

        // Si puede ver analíticas Operativas (Tickets)
        if ($user->can('tickets.analytics')) {
            $kpis['ops'] = [
                'active_tickets' => Ticket::where('status', 'En proceso')->count(),
                'overdue_tickets' => Ticket::where('status', '!=', 'Completado')
                    ->where('scheduled_end', '<', $today)->count(),
            ];
        }

        return Inertia::render('Dashboard', [
            'my_day' => [
                'events' => $myEventsToday,
                'tickets' => $myPendingTickets,
            ],
            'kpis' => $kpis,
        ]);
    }
}