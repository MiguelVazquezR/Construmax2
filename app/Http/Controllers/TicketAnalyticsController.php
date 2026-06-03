<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class TicketAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // 0. Filtros opcionales
        $customerId = $request->input('customer_id');
        $sellerId   = $request->input('seller_id');

        // 1. Rango de Fechas (Default: Este mes)
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfMonth();

        // Helper para aplicar filtro de cliente a queries de tickets
        $applyCustomerFilter = function ($query) use ($customerId) {
            if ($customerId) {
                $query->where('customer_id', $customerId);
            }
            return $query;
        };

        // Helper para aplicar filtro de cliente a queries de budgets (vía ticket)
        $applyCustomerToBudget = function ($query) use ($customerId) {
            if ($customerId) {
                $query->whereHas('ticket', fn($q) => $q->where('customer_id', $customerId));
            }
            return $query;
        };

        // Helper para aplicar filtro de vendedor a queries de tickets
        $applySellerFilter = function ($query) use ($sellerId) {
            if ($sellerId) {
                $query->where('seller_id', $sellerId);
            }
            return $query;
        };

        // Helper para aplicar filtro de vendedor a queries de budgets (vía ticket)
        $applySellerToBudget = function ($query) use ($sellerId) {
            if ($sellerId) {
                $query->whereHas('ticket', fn($q) => $q->where('seller_id', $sellerId));
            }
            return $query;
        };

        // ================================================================
        // SECCIÓN A: KPIs OPERATIVOS (TICKETS)
        // ================================================================
        $ticketsInPeriod = $applySellerFilter(
            $applyCustomerFilter(
                Ticket::whereBetween('scheduled_start', [$startDate, $endDate])
            )
        );

        $totalTickets = $ticketsInPeriod->count();
        $completedTickets = (clone $ticketsInPeriod)->whereIn('status', ['Facturado', 'Ejecutado', 'Pagado'])->count();
        $completionRate = $totalTickets > 0 ? round(($completedTickets / $totalTickets) * 100, 1) : 0;

        $overdueTickets = $applySellerFilter(
            $applyCustomerFilter(
                Ticket::whereBetween('scheduled_end', [$startDate, $endDate])
                    ->whereNotIn('status', ['Facturado', 'Ejecutado', 'Pagado'])
                    ->where('scheduled_end', '<', now())
            )
        )->count();

        // ================================================================
        // SECCIÓN B: KPIs COMERCIALES (CRM — PRESUPUESTOS)
        // ================================================================
        $totalCustomers = Customer::count();
        $newCustomersInRange = Customer::whereBetween('created_at', [$startDate, $endDate])->count();

        $budgetsInRange = $applySellerToBudget(
            $applyCustomerToBudget(
                Budget::whereBetween('created_at', [$startDate, $endDate])
            )
        );
        $totalBudgets = $budgetsInRange->count();
        $wonBudgets = (clone $budgetsInRange)
            ->whereHas('ticket', fn($q) => $q->whereIn('status', ['Facturado', 'Pagado']))
            ->count();
        $conversionRate = $totalBudgets > 0 ? round(($wonBudgets / $totalBudgets) * 100, 1) : 0;

        // Ingresos (pagos cobrados en el periodo, por moneda)
        $paymentsQuery = DB::table('budget_payments')
            ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate]);
        if ($customerId) {
            $paymentsQuery->join('tickets', 'budgets.ticket_id', '=', 'tickets.id')
                ->where('tickets.customer_id', $customerId);
        }
        $paymentsInRange = $paymentsQuery->select('budget_payments.amount', 'budgets.currency')->get();
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

        $activeProjects = $applySellerToBudget(
            $applyCustomerToBudget(
                Budget::whereHas('ticket', fn($q) => $q->where('status', 'Proceso de ejecución'))
            )
        )->count();

        // ================================================================
        // SECCIÓN C: GRÁFICAS DE TICKETS
        // ================================================================

        // C.1 Cronología (área)
        $timelineData = $this->getTimelineChart($startDate, $endDate, $customerId, $sellerId);

        // C.2 Carga por técnico — corregido: ahora usa ticket_tasks.user_id
        $workloadQuery = Ticket::whereBetween('tickets.scheduled_start', [$startDate, $endDate])
            ->join('ticket_tasks', 'tickets.id', '=', 'ticket_tasks.ticket_id')
            ->join('users', 'ticket_tasks.user_id', '=', 'users.id');
        if ($customerId) {
            $workloadQuery->where('tickets.customer_id', $customerId);
        }
        if ($sellerId) {
            $workloadQuery->where('tickets.seller_id', $sellerId);
        }
        $workloadByTech = $workloadQuery
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
        $incomeData = $this->getIncomeChart($startDate, $endDate, $customerId, $sellerId);

        // D.2 Distribución de estatus de presupuestos (donut)
        $statusQuery = Budget::whereBetween('budgets.created_at', [$startDate, $endDate])
            ->join('tickets', 'budgets.ticket_id', '=', 'tickets.id');
        if ($customerId) {
            $statusQuery->where('tickets.customer_id', $customerId);
        }
        if ($sellerId) {
            $statusQuery->where('tickets.seller_id', $sellerId);
        }
        $statusDistribution = $statusQuery
            ->select('tickets.status', DB::raw('count(*) as total'))
            ->groupBy('tickets.status')
            ->pluck('total', 'tickets.status');

        // D.3 Servicios más solicitados (barras horizontales)
        $topServicesQuery = Ticket::whereBetween('scheduled_start', [$startDate, $endDate]);
        if ($customerId) {
            $topServicesQuery->where('customer_id', $customerId);
        }
        if ($sellerId) {
            $topServicesQuery->where('seller_id', $sellerId);
        }
        $topServices = $topServicesQuery
            ->select('service_type', DB::raw('count(*) as total'))
            ->groupBy('service_type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // D.4 Top clientes por ingresos (tabla) — vía Ticket (Budget no tiene customer_id)
        $topCustomersQuery = DB::table('customers')
            ->join('tickets', 'customers.id', '=', 'tickets.customer_id')
            ->join('budgets', 'tickets.id', '=', 'budgets.ticket_id')
            ->join('budget_payments', 'budgets.id', '=', 'budget_payments.budget_id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate]);
        if ($sellerId) {
            $topCustomersQuery->where('tickets.seller_id', $sellerId);
        }
        $topCustomersRaw = $topCustomersQuery
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
        // SECCIÓN E: DISTRIBUCIÓN GEOGRÁFICA (MAPA / BARRAS POR ESTADO)
        // ================================================================
        $regionQuery = Ticket::join('customer_branches', 'tickets.customer_branch_id', '=', 'customer_branches.id')
            ->whereBetween('tickets.scheduled_start', [$startDate, $endDate]);
        if ($customerId) {
            $regionQuery->where('tickets.customer_id', $customerId);
        }
        if ($sellerId) {
            $regionQuery->where('tickets.seller_id', $sellerId);
        }
        $regionDistribution = $regionQuery
            ->select(
                'customer_branches.region',
                'customer_branches.country',
                DB::raw('count(*) as total')
            )
            ->groupBy('customer_branches.region', 'customer_branches.country')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        // ================================================================
        // SECCIÓN F: FACTURAS EMITIDAS vs PAGADAS (PASTEL)
        // ================================================================
        $invoiceDistribution = [];
        // Facturas emitidas: budgets con invoice_date no nulo en el periodo
        $invoicedCount = (clone $budgetsInRange)
            ->whereNotNull('invoice_date')
            ->count();
        // Facturas pagadas: budgets cuyo ticket está en status 'Pagado'
        $paidCount = (clone $budgetsInRange)
            ->whereHas('ticket', fn($q) => $q->where('status', 'Pagado'))
            ->count();
        // Facturadas pero no pagadas
        $invoicedNotPaid = max(0, $invoicedCount - $paidCount);
        // Pendientes de facturar (ni facturadas ni pagadas)
        $pendingInvoice = $totalBudgets - $invoicedCount;

        $invoiceDistribution = [
            'Pagadas'            => $paidCount,
            'Facturadas (cobro pendiente)' => $invoicedNotPaid,
            'Pendientes de facturar'       => max(0, $pendingInvoice),
        ];

        // ================================================================
        // SECCIÓN G: PAGOS A TÉCNICOS EXTERNOS
        // ================================================================
        // Helper para generar folio (misma lógica que Ticket.getFolioAttribute)
        $generateFolio = function ($ticketId, $region, $country) {
            $code = 'UND';
            if ($region || $country) {
                $r = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $region ?? 'X'), 0, 3));
                $c = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $country ?? 'X'), 0, 2));
                $code = "{$r}-{$c}";
            }
            return "#{$ticketId}-{$code}";
        };

        // G.1 Técnicos externos con todas sus tareas completadas y sin pago registrado
        $completedUnpaidQuery = DB::table('ticket_tasks')
            ->join('tickets', 'ticket_tasks.ticket_id', '=', 'tickets.id')
            ->join('users', 'ticket_tasks.user_id', '=', 'users.id')
            ->join('technicians', 'users.id', '=', 'technicians.user_id')
            ->leftJoin('customer_branches', 'tickets.customer_branch_id', '=', 'customer_branches.id')
            ->where('technicians.is_internal', false)
            ->where('ticket_tasks.status', 'Completada')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('technician_payments')
                    ->whereColumn('technician_payments.user_id', 'ticket_tasks.user_id');
            });
        if ($sellerId) {
            $completedUnpaidQuery->where('tickets.seller_id', $sellerId);
        }
        $completedUnpaidRaw = $completedUnpaidQuery
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'tickets.id as ticket_id',
                'tickets.name as ticket_name',
                'customer_branches.region',
                'customer_branches.country'
            )
            ->orderBy('users.name')
            ->get();

        // Agrupar por técnico
        $externalTechsCompletedUnpaid = $completedUnpaidRaw
            ->groupBy('user_id')
            ->map(function ($items) use ($generateFolio) {
                $ticketsGrouped = $items->groupBy('ticket_id')->map(function ($tItems) use ($generateFolio) {
                    $first = $tItems->first();
                    return [
                        'id'    => $first->ticket_id,
                        'name'  => $first->ticket_name,
                        'folio' => $generateFolio($first->ticket_id, $first->region, $first->country),
                    ];
                })->values();

                return [
                    'id'               => $items->first()->user_id,
                    'name'             => $items->first()->user_name,
                    'completed_tickets' => $ticketsGrouped->count(),
                    'completed_tasks'   => $items->count(),
                    'tickets'           => $ticketsGrouped,
                ];
            })
            ->sortByDesc('completed_tasks')
            ->take(5)
            ->values();

        // G.2 Técnicos externos con tareas pendientes (no han terminado)
        $pendingQuery = DB::table('ticket_tasks')
            ->join('tickets', 'ticket_tasks.ticket_id', '=', 'tickets.id')
            ->join('users', 'ticket_tasks.user_id', '=', 'users.id')
            ->join('technicians', 'users.id', '=', 'technicians.user_id')
            ->leftJoin('customer_branches', 'tickets.customer_branch_id', '=', 'customer_branches.id')
            ->where('technicians.is_internal', false)
            ->where('ticket_tasks.status', '!=', 'Completada');
        if ($sellerId) {
            $pendingQuery->where('tickets.seller_id', $sellerId);
        }
        $pendingRaw = $pendingQuery
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'tickets.id as ticket_id',
                'tickets.name as ticket_name',
                'customer_branches.region',
                'customer_branches.country'
            )
            ->orderBy('users.name')
            ->get();

        $externalTechsPending = $pendingRaw
            ->groupBy('user_id')
            ->map(function ($items) use ($generateFolio) {
                $ticketsGrouped = $items->groupBy('ticket_id')->map(function ($tItems) use ($generateFolio) {
                    $first = $tItems->first();
                    return [
                        'id'    => $first->ticket_id,
                        'name'  => $first->ticket_name,
                        'folio' => $generateFolio($first->ticket_id, $first->region, $first->country),
                    ];
                })->values();

                return [
                    'id'              => $items->first()->user_id,
                    'name'            => $items->first()->user_name,
                    'pending_tickets' => $ticketsGrouped->count(),
                    'pending_tasks'   => $items->count(),
                    'tickets'         => $ticketsGrouped,
                ];
            })
            ->sortByDesc('pending_tasks')
            ->take(5)
            ->values();

        // G.3 Conteo rápido: técnicos externos con pago pendiente vs sin terminar
        $techsWithCompletedUnpaidCount = $externalTechsCompletedUnpaid->count();
        $techsWithPendingTasksCount = $externalTechsPending->count();

        // ================================================================
        // SECCIÓN E: TENDENCIAS GLOBALES (SIN FILTRO DE FECHA)
        // ================================================================
        $globalBacklogQuery = Ticket::whereNotIn('status', ['Ejecutado', 'Cancelado']);
        if ($sellerId) {
            $globalBacklogQuery->where('seller_id', $sellerId);
        }
        $globalBacklog = $globalBacklogQuery->count();

        $globalTasksPendingQuery = TicketTask::where('status', '!=', 'Completada');
        if ($sellerId) {
            $globalTasksPendingQuery->whereHas('ticket', fn($q) => $q->where('seller_id', $sellerId));
        }
        $globalTasksPending = $globalTasksPendingQuery->count();

        $techsPendingQuery = DB::table('ticket_tasks')
            ->join('users', 'ticket_tasks.user_id', '=', 'users.id')
            ->join('tickets', 'ticket_tasks.ticket_id', '=', 'tickets.id')
            ->where('ticket_tasks.status', '!=', 'Completada');
        if ($sellerId) {
            $techsPendingQuery->where('tickets.seller_id', $sellerId);
        }
        $techsWithPendingTasks = $techsPendingQuery
            ->select('users.name', DB::raw('count(*) as pending_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('pending_count')
            ->limit(5)
            ->get();

        // ================================================================
        // RESPUESTA
        // ================================================================
        return Inertia::render('TicketsDashboard', [
            'customers' => Customer::select('id', 'name')->orderBy('name')->get(),
            'sellers'   => User::whereHas('ticketsAsSeller')->select('id', 'name')->orderBy('name')->get(),
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
                // Técnicos externos
                'techs_completed_unpaid' => $techsWithCompletedUnpaidCount,
                'techs_pending_tasks'    => $techsWithPendingTasksCount,
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
                // Nuevos
                'regions'   => $regionDistribution,
                'invoices'  => $invoiceDistribution,
            ],
            'tables' => [
                'top_customers'               => $topCustomers,
                'external_techs_completed'     => $externalTechsCompletedUnpaid,
                'external_techs_pending'       => $externalTechsPending,
            ],
            'general' => [
                'backlog'        => $globalBacklog,
                'pending_tasks'  => $globalTasksPending,
                'busy_techs'     => $techsWithPendingTasks,
            ],
            'filters' => [
                'start_date'  => $startDate->toDateString(),
                'end_date'    => $endDate->toDateString(),
                'customer_id' => $customerId,
                'seller_id'   => $sellerId,
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

    private function getTimelineChart($startDate, $endDate, $customerId = null, $sellerId = null): array
    {
        $diffInDays = $startDate->diffInDays($endDate);
        $labels = [];
        $createdData = [];

        if ($diffInDays > 60) {
            $period = \Carbon\CarbonPeriod::create($startDate, '1 month', $endDate);
            foreach ($period as $dt) {
                $labels[] = ucfirst($dt->translatedFormat('M Y'));
                $query = Ticket::whereYear('scheduled_start', $dt->year)
                    ->whereMonth('scheduled_start', $dt->month);
                if ($customerId) $query->where('customer_id', $customerId);
                if ($sellerId) $query->where('seller_id', $sellerId);
                $createdData[] = $query->count();
            }
        } else {
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $dt) {
                $labels[] = $dt->format('d/m');
                $query = Ticket::whereDate('scheduled_start', $dt->toDateString());
                if ($customerId) $query->where('customer_id', $customerId);
                if ($sellerId) $query->where('seller_id', $sellerId);
                $createdData[] = $query->count();
            }
        }

        return [
            'labels' => $labels,
            'series' => [
                ['name' => 'Programados', 'data' => $createdData],
            ],
        ];
    }

    private function getIncomeChart($startDate, $endDate, $customerId = null, $sellerId = null): array
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
                ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id');
            if ($customerId || $sellerId) {
                $query->join('tickets', 'budgets.ticket_id', '=', 'tickets.id');
            }
            if ($customerId) {
                $query->where('tickets.customer_id', $customerId);
            }
            if ($sellerId) {
                $query->where('tickets.seller_id', $sellerId);
            }
            $query->select('budget_payments.amount', 'budgets.currency');

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