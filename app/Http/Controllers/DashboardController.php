<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $kpis['crm'] = [
                'customers_month' => Customer::whereMonth('created_at', $today->month)->count(),
                'budgets_pending' => Budget::whereIn('status', ['Presupuesto enviado', 'Trabajo en proceso'])->count(),
                'sales_month' => Budget::where('status', 'Pagado')
                    ->join('budget_payments', 'budgets.id', '=', 'budget_payments.budget_id')
                    ->whereMonth('budget_payments.payment_date', $today->month)
                    ->sum('budget_payments.amount'),
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