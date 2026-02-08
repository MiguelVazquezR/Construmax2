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
        // 1. Definir Rango de Fechas (Default: Este mes)
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay() 
            : Carbon::now()->startOfMonth();
            
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date'))->endOfDay() 
            : Carbon::now()->endOfMonth();

        // 2. KPIs Generales
        $totalCustomers = Customer::count();
        $newCustomersInRange = Customer::whereBetween('created_at', [$startDate, $endDate])->count();
        
        // Presupuestos
        $budgetsInRange = Budget::whereBetween('created_at', [$startDate, $endDate]);
        $totalBudgets = $budgetsInRange->count();
        $wonBudgets = (clone $budgetsInRange)->whereIn('status', ['Facturado', 'Trabajo en proceso', 'Trabajo terminado', 'Pagado'])->count();
        $conversionRate = $totalBudgets > 0 ? round(($wonBudgets / $totalBudgets) * 100, 1) : 0;

        // --- LÓGICA MULTI-MONEDA PARA INGRESOS (REVENUE) ---
        // Cargamos la relación 'budget' para obtener la moneda desde ahí
        $paymentsInRange = BudgetPayment::with('budget')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->get();
            
        $revenue = $this->calculateMultiCurrencyTotal($paymentsInRange);
        
        // --- LÓGICA PARA SALDO PENDIENTE (PENDING BALANCE) ---
        $budgetsIds = (clone $budgetsInRange)->pluck('id');
        
        $totalEstimated = DB::table('budget_concepts')->whereIn('budget_id', $budgetsIds)->sum('amount');
        
        // Restamos lo que ya se pagó de ESOS presupuestos específicos
        $paidFromThoseBudgets = BudgetPayment::with('budget')
            ->whereIn('budget_id', $budgetsIds)
            ->get();
            
        $paidTotal = $this->calculateMultiCurrencyTotal($paidFromThoseBudgets);
        
        $pendingBalance = [
            'mxn' => $totalEstimated - $paidTotal['mxn'],
            'usd' => ($totalEstimated / 20) - $paidTotal['usd'] // Estimación si no hay tasa en conceptos
        ];

        // 3. Gráfica: Ingresos en el tiempo
        $incomeData = $this->getIncomeChart($startDate, $endDate);

        // 4. Gráfica: Distribución de Estatus
        $statusDistribution = Budget::whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // 5. Gráfica: Top Servicios
        $topServices = Budget::whereBetween('created_at', [$startDate, $endDate])
            ->select('service_type', DB::raw('count(*) as total'))
            ->groupBy('service_type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 6. Tabla: Top Clientes (Multi-moneda)
        // CORRECCIÓN: Obtenemos currency y exchange_rate de la tabla 'budgets'
        $topCustomersRaw = DB::table('customers')
            ->join('budgets', 'customers.id', '=', 'budgets.customer_id')
            ->join('budget_payments', 'budgets.id', '=', 'budget_payments.budget_id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate])
            ->select(
                'customers.name', 
                'customers.id', 
                'budget_payments.amount', 
                'budgets.currency',         // CORREGIDO: Viene de budgets
                'budgets.exchange_rate'     // CORREGIDO: Viene de budgets
            )
            ->get();

        $customersMap = [];
        foreach ($topCustomersRaw as $row) {
            if (!isset($customersMap[$row->id])) {
                $customersMap[$row->id] = [
                    'name' => $row->name, 
                    'total_mxn' => 0, 
                    'total_usd' => 0
                ];
            }
            
            // Usamos operador null coalescing por seguridad
            $rate = ($row->exchange_rate ?? 0) > 0 ? $row->exchange_rate : 1;
            $currency = $row->currency ?? 'MXN';

            if ($currency === 'USD') {
                $customersMap[$row->id]['total_usd'] += $row->amount;
                $customersMap[$row->id]['total_mxn'] += $row->amount * $rate;
            } else {
                $customersMap[$row->id]['total_mxn'] += $row->amount;
                $customersMap[$row->id]['total_usd'] += $row->amount / $rate;
            }
        }

        // Ordenar y limitar
        $topCustomers = collect($customersMap)->sortByDesc('total_mxn')->take(5)->values();

        return Inertia::render('CRMDashboard', [
            'kpis' => [
                'total_customers' => $totalCustomers,
                'new_customers' => $newCustomersInRange,
                'conversion_rate' => $conversionRate,
                'total_revenue' => $revenue, 
                'pending_balance' => $pendingBalance,
                'active_projects' => Budget::where('status', 'Trabajo en proceso')->count(),
            ],
            'charts' => [
                'income' => $incomeData,
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
     * Helper para sumar colecciones de pagos considerando moneda y tipo de cambio
     */
    private function calculateMultiCurrencyTotal($payments)
    {
        $mxn = 0;
        $usd = 0;

        foreach ($payments as $payment) {
            $amount = $payment->amount;
            
            // Accedemos a la moneda a través de la relación con Budget
            if (!$payment->budget) continue;

            $currency = $payment->budget->currency ?? 'MXN';
            $rate = ($payment->budget->exchange_rate ?? 0) > 0 ? $payment->budget->exchange_rate : 1; 

            if ($currency === 'USD') {
                $usd += $amount;
                $mxn += $amount * $rate;
            } else {
                $mxn += $amount;
                $usd += $amount / $rate;
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

            // Consultar pagos de ese intervalo
            $query = BudgetPayment::with('budget'); // Importante cargar relación
            
            if ($groupBy === 'month') {
                $query->whereYear('payment_date', $dt->year)
                      ->whereMonth('payment_date', $dt->month);
            } else {
                $query->whereDate('payment_date', $dt->toDateString());
            }

            $payments = $query->get();
            $totals = $this->calculateMultiCurrencyTotal($payments);

            $dataMXN[] = round($totals['mxn'], 2);
            $dataUSD[] = round($totals['usd'], 2);
        }

        return [
            'labels' => $labels, 
            'data_mxn' => $dataMXN,
            'data_usd' => $dataUSD
        ];
    }
}