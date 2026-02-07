<?php

namespace App\Http\Controllers;

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
        // 1. Rango de Fechas (Default: Este mes)
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay() 
            : Carbon::now()->startOfMonth();
            
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date'))->endOfDay() 
            : Carbon::now()->endOfMonth();

        // 2. KPIs del Periodo (Basados en fecha de programación de inicio)
        $ticketsInPeriod = Ticket::whereBetween('scheduled_start', [$startDate, $endDate]);
        
        $totalTickets = $ticketsInPeriod->count();
        $completedTickets = (clone $ticketsInPeriod)->where('status', 'Completado')->count();
        
        // Tasa de cumplimiento (Tickets completados vs Total programados en el periodo)
        $completionRate = $totalTickets > 0 ? round(($completedTickets / $totalTickets) * 100, 1) : 0;

        // Tickets vencidos en el periodo (No completados y fecha fin < hoy)
        // Nota: Solo contamos los que debieron terminar en este periodo
        $overdueTickets = Ticket::whereBetween('scheduled_end', [$startDate, $endDate])
            ->where('status', '!=', 'Completado')
            ->where('scheduled_end', '<', now())
            ->count();

        // 3. Gráfica: Tickets por Día/Mes (Cronología)
        $timelineData = $this->getTimelineChart($startDate, $endDate);

        // 4. Gráfica: Carga por Técnico (Top 5 con más tickets asignados en el periodo)
        $workloadByTech = Ticket::whereBetween('scheduled_start', [$startDate, $endDate])
            ->join('users', 'tickets.user_id', '=', 'users.id')
            ->select('users.name', DB::raw('count(*) as total'))
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(7)
            ->get();

        // 5. Gráfica: Tickets por Prioridad
        $ticketsByPriority = (clone $ticketsInPeriod)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        // 6. TENDENCIAS GENERALES (Histórico sin filtros)
        $globalBacklog = Ticket::whereNotIn('status', ['Completado', 'Cancelado'])->count();
        $globalTasksPending = TicketTask::where('status', '!=', 'Completada')->count();
        
        // Tabla: Técnicos con más tareas pendientes (Carga actual real)
        $techsWithPendingTasks = DB::table('ticket_tasks')
            ->join('users', 'ticket_tasks.user_id', '=', 'users.id')
            ->where('ticket_tasks.status', '!=', 'Completada')
            ->select('users.name', DB::raw('count(*) as pending_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('pending_count')
            ->limit(5)
            ->get();

        return Inertia::render('TicketsDashboard', [
            'kpis' => [
                'total_tickets' => $totalTickets,
                'completed_tickets' => $completedTickets,
                'completion_rate' => $completionRate,
                'overdue_tickets' => $overdueTickets,
            ],
            'charts' => [
                'timeline' => $timelineData,
                'workload' => $workloadByTech,
                'priority' => $ticketsByPriority,
            ],
            'general' => [
                'backlog' => $globalBacklog,
                'pending_tasks' => $globalTasksPending,
                'busy_techs' => $techsWithPendingTasks,
            ],
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]
        ]);
    }

    private function getTimelineChart($startDate, $endDate)
    {
        $diffInDays = $startDate->diffInDays($endDate);
        $labels = [];
        $createdData = [];
        $completedData = []; // Aproximación basada en update_at si status es completed

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
            ]
        ];
    }
}