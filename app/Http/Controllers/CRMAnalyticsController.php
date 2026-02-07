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

        // 2. KPIs Generales (Filtrados por creación en el rango)
        $totalCustomers = Customer::count(); // Total histórico (generalmente no se filtra por rango, pero los nuevos sí)
        
        $newCustomersInRange = Customer::whereBetween('created_at', [$startDate, $endDate])->count();
        
        // Presupuestos creados en el rango
        $budgetsInRange = Budget::whereBetween('created_at', [$startDate, $endDate]);
        $totalBudgets = $budgetsInRange->count();
        
        $wonBudgets = (clone $budgetsInRange)->whereIn('status', ['Facturado', 'Trabajo en proceso', 'Trabajo terminado', 'Pagado'])->count();
        $conversionRate = $totalBudgets > 0 ? round(($wonBudgets / $totalBudgets) * 100, 1) : 0;

        // Finanzas (Pagos recibidos en el rango)
        $totalRevenue = BudgetPayment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount');
        
        // Saldo pendiente (De presupuestos creados en el rango)
        // Nota: Esto responde a "¿Cuánto me deben de lo que vendí este mes?"
        $budgetsIds = (clone $budgetsInRange)->pluck('id');
        $totalEstimatedCost = DB::table('budget_concepts')->whereIn('budget_id', $budgetsIds)->sum('amount');
        $revenueFromThoseBudgets = DB::table('budget_payments')->whereIn('budget_id', $budgetsIds)->sum('amount');
        $pendingBalance = $totalEstimatedCost - $revenueFromThoseBudgets;


        // 3. Gráfica: Ingresos en el tiempo (Dinámico: Por día o Por mes)
        $incomeData = $this->getIncomeChart($startDate, $endDate);

        // 4. Gráfica: Distribución de Estatus (De presupuestos creados en el rango)
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

        // 6. Tabla: Top Clientes (Pagos en el rango)
        $topCustomers = DB::table('customers')
            ->join('budgets', 'customers.id', '=', 'budgets.customer_id')
            ->join('budget_payments', 'budgets.id', '=', 'budget_payments.budget_id')
            ->whereBetween('budget_payments.payment_date', [$startDate, $endDate])
            ->select('customers.name', 'customers.id', DB::raw('sum(budget_payments.amount) as total_paid'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_paid')
            ->limit(5)
            ->get();

        return Inertia::render('CRMDashboard', [
            'kpis' => [
                'total_customers' => $totalCustomers,
                'new_customers' => $newCustomersInRange,
                'conversion_rate' => $conversionRate,
                'total_revenue' => $totalRevenue,
                'pending_balance' => $pendingBalance,
                'active_projects' => Budget::where('status', 'Trabajo en proceso')->count(), // Histórico activo
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

    private function getIncomeChart($startDate, $endDate)
    {
        $diffInDays = $startDate->diffInDays($endDate);
        
        $labels = [];
        $data = [];

        // Si el rango es mayor a 60 días, agrupamos por Mes
        if ($diffInDays > 60) {
            $period = \Carbon\CarbonPeriod::create($startDate, '1 month', $endDate);
            foreach ($period as $dt) {
                $labels[] = ucfirst($dt->translatedFormat('M Y'));
                $amount = BudgetPayment::whereYear('payment_date', $dt->year)
                    ->whereMonth('payment_date', $dt->month)
                    ->sum('amount');
                $data[] = $amount;
            }
        } 
        // Si es corto, agrupamos por Día
        else {
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $dt) {
                $labels[] = $dt->format('d/m');
                $amount = BudgetPayment::whereDate('payment_date', $dt->toDateString())
                    ->sum('amount');
                $data[] = $amount;
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }
}