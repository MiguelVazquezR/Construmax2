<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class TicketAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // 1. Rango de Fechas (Default: Este mes)
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfMonth();

        // ================================================================
        // SECCIÓN A: KPIs OPERATIVOS (TICKETS)
        // ================================================================
        $ticketsInPeriod = Ticket::whereBetween('scheduled_start', [$startDate, $endDate]);

        $totalTickets = $ticketsInPeriod->count();
        $completedTickets = (clone $ticketsInPeriod)->where('status', 'Ejecutado')->count();
        $completionRate = $totalTickets > 0 ? round(($completedTickets / $totalTickets) * 100, 1) : 0;

        $overdueTickets = Ticket::whereBetween('scheduled_end', [$startDate, $endDate])
            ->where('status', '!=', 'Ejecutado')
            ->where('scheduled_end', '<', now())
            ->count();

        // ================================================================
        // SECCIÓN B: KPIs COMERCIALES (CRM — PRESUPUESTOS)
        // ================================================================
        $totalCustomers = Customer::count();
        $newCustomersInRange = Customer::whereBetween('created_at', [$startDate, $endDate])->count();

        $budgetsInRange = Budget::whereBetween('created_at', [$startDate, $endDate]);
        $totalBudgets = $budgetsInRange->count();
        $wonBudgets = (clone $budgetsInRange)
            ->whereHas('ticket', fn($q) => $q->whereIn('status', ['Facturado', 'Pagado']))
            ->count();
        $conversionRate = $totalBudgets > 0 ? round(($wonBudgets / $totalBudgets) * 100, 1) : 0;

        // Ingresos (pagos cobrados en el periodo, por moneda)
        $paymentsInRange = DB::table('budget_payments')
            ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate])
            ->select('budget_payments.amount', 'budgets.currency')
            ->get();
        $revenue = $this->calculateCurrencyBuckets($paymentsInRange);

        // Saldo pendiente de presupuestos creados en el periodo
        $budgetsIds = (clone $budgetsInRange)->pluck('id');
        $budgetConcepts = DB::table('budget_concepts')
            ->join('budgets', 'budget_concepts.budget_id', '=', 'budgets.id')
            ->whereIn('budget_concepts.budget_id', $budgetsIds)
            ->select('budget_concepts.amount', 'budgets.currency')
            ->get();
        $totalBudgeted = $this->calculateCurrencyBuckets($budgetConcepts);

        $paidForBudgets = DB::table('budget_payments')
            ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
            ->whereIn('budget_payments.budget_id', $budgetsIds)
            ->select('budget_payments.amount', 'budgets.currency')
            ->get();
        $totalPaidForBudgets = $this->calculateCurrencyBuckets($paidForBudgets);

        $pendingBalance = [
            'mxn' => $totalBudgeted['mxn'] - $totalPaidForBudgets['mxn'],
            'usd' => $totalBudgeted['usd'] - $totalPaidForBudgets['usd'],
        ];

        $activeProjects = Budget::whereHas('ticket', fn($q) => $q->where('status', 'Proceso de ejecución'))->count();

        // ================================================================
        // SECCIÓN C: GRÁFICAS DE TICKETS
        // ================================================================

        // C.1 Cronología (área)
        $timelineData = $this->getTimelineChart($startDate, $endDate);

        // C.2 Carga por técnico — corregido: ahora usa ticket_tasks.user_id
        $workloadByTech = Ticket::whereBetween('tickets.scheduled_start', [$startDate, $endDate])
            ->join('ticket_tasks', 'tickets.id', '=', 'ticket_tasks.ticket_id')
            ->join('users', 'ticket_tasks.user_id', '=', 'users.id')
            ->select('users.name', DB::raw('count(distinct tickets.id) as total'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(7)
            ->get();

        // C.3 Prioridad (donut)
        $ticketsByPriority = (clone $ticketsInPeriod)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        // ================================================================
        // SECCIÓN D: GRÁFICAS COMERCIALES (CRM)
        // ================================================================

        // D.1 Ingresos por día/mes (barras, separado MXN/USD)
        $incomeData = $this->getIncomeChart($startDate, $endDate);

        // D.2 Distribución de estatus de presupuestos (donut)
        $statusDistribution = Budget::whereBetween('budgets.created_at', [$startDate, $endDate])
            ->join('tickets', 'budgets.ticket_id', '=', 'tickets.id')
            ->select('tickets.status', DB::raw('count(*) as total'))
            ->groupBy('tickets.status')
            ->pluck('total', 'tickets.status');

        // D.3 Servicios más solicitados (barras horizontales)
        $topServices = Ticket::whereBetween('scheduled_start', [$startDate, $endDate])
            ->select('service_type', DB::raw('count(*) as total'))
            ->groupBy('service_type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // D.4 Top clientes por ingresos (tabla) — vía Ticket (Budget no tiene customer_id)
        $topCustomersRaw = DB::table('customers')
            ->join('tickets', 'customers.id', '=', 'tickets.customer_id')
            ->join('budgets', 'tickets.id', '=', 'budgets.ticket_id')
            ->join('budget_payments', 'budgets.id', '=', 'budget_payments.budget_id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate])
            ->select('customers.name', 'customers.id', 'budget_payments.amount', 'budgets.currency')
            ->get();

        $customersMap = [];
        foreach ($topCustomersRaw as $row) {
            if (!isset($customersMap[$row->id])) {
                $customersMap[$row->id] = ['name' => $row->name, 'total_mxn' => 0, 'total_usd' => 0];
            }
            $currency = $row->currency ?? 'MXN';
            if ($currency === 'USD') {
                $customersMap[$row->id]['total_usd'] += $row->amount;
            } else {
                $customersMap[$row->id]['total_mxn'] += $row->amount;
            }
        }
        $topCustomers = collect($customersMap)
            ->sortByDesc(fn($c) => $c['total_mxn'] + ($c['total_usd'] * 20))
            ->take(5)
            ->values();

        // ================================================================
        // SECCIÓN E: TENDENCIAS GLOBALES (SIN FILTRO DE FECHA)
        // ================================================================
        $globalBacklog = Ticket::whereNotIn('status', ['Ejecutado', 'Cancelado'])->count();
        $globalTasksPending = TicketTask::where('status', '!=', 'Completada')->count();

        $techsWithPendingTasks = DB::table('ticket_tasks')
            ->join('users', 'ticket_tasks.user_id', '=', 'users.id')
            ->where('ticket_tasks.status', '!=', 'Completada')
            ->select('users.name', DB::raw('count(*) as pending_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('pending_count')
            ->limit(5)
            ->get();

        // ================================================================
        // RESPUESTA
        // ================================================================
        return Inertia::render('TicketsDashboard', [
            'kpis' => [
                // Tickets
                'total_tickets'      => $totalTickets,
                'completed_tickets'  => $completedTickets,
                'completion_rate'    => $completionRate,
                'overdue_tickets'    => $overdueTickets,
                // CRM
                'total_customers'    => $totalCustomers,
                'new_customers'      => $newCustomersInRange,
                'conversion_rate'    => $conversionRate,
                'total_revenue'      => $revenue,
                'pending_balance'    => $pendingBalance,
                'active_projects'    => $activeProjects,
            ],
            'charts' => [
                // Tickets
                'timeline'  => $timelineData,
                'workload'  => $workloadByTech,
                'priority'  => $ticketsByPriority,
                // CRM
                'income'    => $incomeData,
                'status'    => $statusDistribution,
                'services'  => $topServices,
            ],
            'tables' => [
                'top_customers' => $topCustomers,
            ],
            'general' => [
                'backlog'        => $globalBacklog,
                'pending_tasks'  => $globalTasksPending,
                'busy_techs'     => $techsWithPendingTasks,
            ],
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date'   => $endDate->toDateString(),
            ],
        ]);
    }

    // ─── HELPERS ───────────────────────────────────────────────────────

    private function calculateCurrencyBuckets($items): array
    {
        $mxn = 0;
        $usd = 0;

        foreach ($items as $item) {
            $currency = $item->currency ?? 'MXN';
            if ($currency === 'USD') {
                $usd += $item->amount;
            } else {
                $mxn += $item->amount;
            }
        }

        return ['mxn' => $mxn, 'usd' => $usd];
    }

    private function getTimelineChart($startDate, $endDate): array
    {
        $diffInDays = $startDate->diffInDays($endDate);
        $labels = [];
        $createdData = [];

        if ($diffInDays > 60) {
            $period = \Carbon\CarbonPeriod::create($startDate, '1 month', $endDate);
            foreach ($period as $dt) {
                $labels[] = ucfirst($dt->translatedFormat('M Y'));
                $createdData[] = Ticket::whereYear('scheduled_start', $dt->year)
                    ->whereMonth('scheduled_start', $dt->month)
                    ->count();
            }
        } else {
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $dt) {
                $labels[] = $dt->format('d/m');
                $createdData[] = Ticket::whereDate('scheduled_start', $dt->toDateString())
                    ->count();
            }
        }

        return [
            'labels' => $labels,
            'series' => [
                ['name' => 'Programados', 'data' => $createdData],
            ],
        ];
    }

    private function getIncomeChart($startDate, $endDate): array
    {
        $diffInDays = $startDate->diffInDays($endDate);
        $labels = [];
        $dataMXN = [];
        $dataUSD = [];

        if ($diffInDays > 60) {
            $period = \Carbon\CarbonPeriod::create($startDate, '1 month', $endDate);
            $groupBy = 'month';
        } else {
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            $groupBy = 'day';
        }

        foreach ($period as $dt) {
            $labels[] = $groupBy === 'month'
                ? ucfirst($dt->translatedFormat('M Y'))
                : $dt->format('d/m');

            $query = DB::table('budget_payments')
                ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
                ->select('budget_payments.amount', 'budgets.currency');

            if ($groupBy === 'month') {
                $query->whereYear('payment_date', $dt->year)
                      ->whereMonth('payment_date', $dt->month);
            } else {
                $query->whereDate('payment_date', $dt->toDateString());
            }

            $totals = $this->calculateCurrencyBuckets($query->get());
            $dataMXN[] = $totals['mxn'];
            $dataUSD[] = $totals['usd'];
        }

        return [
            'labels'    => $labels,
            'data_mxn'  => $dataMXN,
            'data_usd'  => $dataUSD,
        ];
    }
}