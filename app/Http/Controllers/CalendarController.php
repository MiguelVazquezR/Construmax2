<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\User;
use App\Models\CustomerBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function overview()
    {
        $userId = Auth::id();
        $today = now()->startOfDay();
        $endToday = now()->endOfDay();

        $pendingInvitations = DB::table('calendar_participants')
            ->where('user_id', $userId)
            ->where('status', 'Pendiente')
            ->count();

        // Eventos de hoy que NO están completados
        $todaysEvents = Calendar::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereHas('participants', function ($q) use ($userId) {
                          $q->where('user_id', $userId)->where('status', 'Aceptado');
                      });
            })
            ->where('is_completed', false) // FILTRO CRÍTICO: Solo contar pendientes
            ->where(function ($q) use ($today, $endToday) {
                $q->whereBetween('start_time', [$today, $endToday])
                  ->orWhere(function($sub) use ($today) {
                      $sub->where('start_time', '<', $today)
                          ->where('end_time', '>', $today);
                  });
            })
            ->count();

        return response()->json([
            'invitations' => $pendingInvitations,
            'today_events' => $todaysEvents,
            'total' => $pendingInvitations + $todaysEvents
        ]);
    }

    public function index()
    {
        $userId = Auth::id();

        $events = Calendar::with(['creator', 'participants'])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereHas('participants', function ($q) use ($userId) {
                          $q->where('user_id', $userId);
                      });
            })
            ->get()
            ->map(function ($event) use ($userId) {
                $myParticipation = $event->participants->find($userId);
                $status = $event->user_id === $userId ? 'Creador' : ($myParticipation ? $myParticipation->pivot->status : 'Desconocido');
                
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'type' => $event->type,
                    'description' => $event->description,
                    'start' => $event->start_time->toDateTimeString(),
                    'end' => $event->end_time->toDateTimeString(),
                    'is_completed' => $event->is_completed,
                    'creator' => $event->creator,
                    'participants' => $event->participants,
                    'my_status' => $status,
                    'is_creator' => $event->user_id === $userId,
                ];
            });

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'users' => User::where('id', '!=', $userId)->where('is_active', true)->get(),
            'technicians' => User::whereHas('technician')->with('technician')->orderBy('name')->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'is_internal' => $u->technician?->is_internal ?? false,
                ]),
            'branches' => CustomerBranch::with('customer')
                ->orderBy('branch_name')
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'branch_name' => $b->branch_name,
                    'customer_name' => $b->customer?->name,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'description' => 'nullable|string',
            'participants' => 'array',
        ]);

        DB::transaction(function () use ($validated) {
            $calendar = Calendar::create([
                'user_id' => Auth::id(),
                'title' => $validated['title'],
                'type' => $validated['type'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'description' => $validated['description'],
                'is_completed' => false,
            ]);

            if (!empty($validated['participants'])) {
                $calendar->participants()->attach($validated['participants'], ['status' => 'Pendiente']);
            }
        });

        return back()->with('success', 'Evento agendado correctamente.');
    }

    public function update(Request $request, Calendar $calendar)
    {
        if ($calendar->user_id !== Auth::id()) {
            abort(403, 'Solo el creador puede editar el evento.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'description' => 'nullable|string',
            'participants' => 'array',
        ]);

        DB::transaction(function () use ($validated, $calendar) {
            $calendar->update([
                'title' => $validated['title'],
                'type' => $validated['type'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'description' => $validated['description'],
            ]);

            $calendar->participants()->sync($validated['participants']);
        });

        return back()->with('success', 'Evento actualizado.');
    }

    // NUEVO MÉTODO
    public function toggleComplete(Calendar $calendar)
    {
        // Permitimos que el creador marque como terminado
        // Opcional: permitir que participantes también lo hagan dependiendo de tu lógica de negocio
        if ($calendar->user_id !== Auth::id()) {
             // Si quieres que participantes puedan terminar tareas, elimina este if o adáptalo
             // abort(403, 'Solo el creador puede finalizar el evento.');
        }

        $calendar->update(['is_completed' => !$calendar->is_completed]);
        
        $statusMsg = $calendar->is_completed ? 'Evento marcado como terminado.' : 'Evento reactivado.';
        return back()->with('success', $statusMsg);
    }

    public function destroy(Calendar $calendar)
    {
        if ($calendar->user_id !== Auth::id()) {
            abort(403);
        }
        $calendar->delete();
        return back()->with('success', 'Evento eliminado.');
    }

    public function respond(Request $request, Calendar $calendar)
    {
        $validated = $request->validate([
            'status' => 'required|in:Aceptado,Rechazado',
            'rejection_reason' => 'nullable|required_if:status,Rechazado|string',
        ]);

        $calendar->participants()->updateExistingPivot(Auth::id(), [
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'Rechazado' ? $validated['rejection_reason'] : null,
        ]);

        return back()->with('success', 'Has respondido a la invitación.');
    }
}