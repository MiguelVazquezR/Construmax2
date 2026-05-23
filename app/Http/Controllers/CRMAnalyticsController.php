<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetPayment;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class CRMAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // 1. Definir Rango de Fechas
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay() 
            : Carbon::now()->startOfMonth();
            
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date'))->endOfDay() 
            : Carbon::now()->endOfMonth();

        // 2. KPIs Generales
        $totalCustomers = Customer::count();
        $newCustomersInRange = Customer::whereBetween('created_at', [$startDate, $endDate])->count();
        
        $budgetsInRange = Budget::whereBetween('created_at', [$startDate, $endDate]);
        $totalBudgets = $budgetsInRange->count();
        $wonBudgets = (clone $budgetsInRange)->whereIn('status', ['Facturado', 'Pagado'])->count();
        $conversionRate = $totalBudgets > 0 ? round(($wonBudgets / $totalBudgets) * 100, 1) : 0;

        // --- LÓGICA DE INGRESOS (REVENUE BUCKETS) ---
        // Sumamos lo cobrado en el periodo, separado por moneda, SIN CONVERTIR
        $paymentsInRange = DB::table('budget_payments')
            ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate])
            ->select('budget_payments.amount', 'budgets.currency')
            ->get();

        $revenue = $this->calculateCurrencyBuckets($paymentsInRange);
        
        // --- LÓGICA DE SALDO PENDIENTE (PENDING BALANCE BUCKETS) ---
        // "De lo que vendí en este periodo, ¿cuánto me falta cobrar en cada moneda?"
        
        // 1. Calcular el valor total de los presupuestos creados en el rango
        $budgetsIds = (clone $budgetsInRange)->pluck('id');
        
        $budgetConcepts = DB::table('budget_concepts')
            ->join('budgets', 'budget_concepts.budget_id', '=', 'budgets.id')
            ->whereIn('budget_concepts.budget_id', $budgetsIds)
            ->select('budget_concepts.amount', 'budgets.currency')
            ->get();
            
        $totalBudgeted = $this->calculateCurrencyBuckets($budgetConcepts);

        // 2. Calcular cuánto se ha pagado DE ESOS presupuestos (histórico total)
        $paidForBudgets = DB::table('budget_payments')
            ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
            ->whereIn('budget_payments.budget_id', $budgetsIds)
            ->select('budget_payments.amount', 'budgets.currency')
            ->get();
            
        $totalPaidForBudgets = $this->calculateCurrencyBuckets($paidForBudgets);

        $pendingBalance = [
            'mxn' => $totalBudgeted['mxn'] - $totalPaidForBudgets['mxn'],
            'usd' => $totalBudgeted['usd'] - $totalPaidForBudgets['usd']
        ];

        // 3. Gráficas
        $incomeData = $this->getIncomeChart($startDate, $endDate);

        $statusDistribution = Budget::whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $topServices = Budget::whereBetween('created_at', [$startDate, $endDate])
            ->select('service_type', DB::raw('count(*) as total'))
            ->groupBy('service_type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 4. Tabla: Top Clientes (Buckets)
        $topCustomersRaw = DB::table('customers')
            ->join('budgets', 'customers.id', '=', 'budgets.customer_id')
            ->join('budget_payments', 'budgets.id', '=', 'budget_payments.budget_id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate])
            ->select(
                'customers.name', 
                'customers.id', 
                'budget_payments.amount', 
                'budgets.currency'
            )
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

        // Ordenamos por una mezcla ponderada solo para el ranking (MXN + USD*20), pero mostramos los valores reales
        $topCustomers = collect($customersMap)
            ->sortByDesc(fn($c) => $c['total_mxn'] + ($c['total_usd'] * 20)) 
            ->take(5)
            ->values();

        return Inertia::render('CRMDashboard', [
            'kpis' => [
                'total_customers' => $totalCustomers,
                'new_customers' => $newCustomersInRange,
                'conversion_rate' => $conversionRate,
                'total_revenue' => $revenue, // {mxn, usd}
                'pending_balance' => $pendingBalance, // {mxn, usd}
                'active_projects' => Budget::where('status', 'Trabajo en proceso')->count(),
            ],
            'charts' => [
                'income' => $incomeData, // {labels, data_mxn, data_usd}
                'status' => $statusDistribution,
                'services' => $topServices,
            ],
            'tables' => [
                'top_customers' => $topCustomers,
            ],
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]
        ]);
    }

    /**
     * Suma los montos en sus respectivas bolsas (MXN o USD) sin convertir.
     */
    private function calculateCurrencyBuckets($items)
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

    private function getIncomeChart($startDate, $endDate)
    {
        $diffInDays = $startDate->diffInDays($endDate);
        
        $labels = [];
        $dataMXN = [];
        $dataUSD = [];

        // Definir periodo
        if ($diffInDays > 60) {
            $period = \Carbon\CarbonPeriod::create($startDate, '1 month', $endDate);
            $format = 'M Y';
            $groupBy = 'month';
        } else {
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            $format = 'd/m';
            $groupBy = 'day';
        }

        foreach ($period as $dt) {
            $labels[] = ucfirst($dt->translatedFormat($format));

            $query = DB::table('budget_payments')
                ->join('budgets', 'budget_payments.budget_id', '=', 'budgets.id')
                ->select('budget_payments.amount', 'budgets.currency');
            
            if ($groupBy === 'month') {
                $query->whereYear('payment_date', $dt->year)
                      ->whereMonth('payment_date', $dt->month);
            } else {
                $query->whereDate('payment_date', $dt->toDateString());
            }

            $payments = $query->get();
            $totals = $this->calculateCurrencyBuckets($payments);

            $dataMXN[] = $totals['mxn'];
            $dataUSD[] = $totals['usd'];
        }

        return [
            'labels' => $labels, 
            'data_mxn' => $dataMXN,
            'data_usd' => $dataUSD
        ];
    }
}