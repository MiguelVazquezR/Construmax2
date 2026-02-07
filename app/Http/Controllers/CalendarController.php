<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CalendarController extends Controller
{
    // --- NUEVO MÉTODO PARA EL BADGE ---
    public function overview()
    {
        $userId = Auth::id();
        $today = now()->startOfDay();
        $endToday = now()->endOfDay();

        // 1. Invitaciones pendientes (Donde me invitaron y no he respondido)
        $pendingInvitations = DB::table('calendar_participants')
            ->where('user_id', $userId)
            ->where('status', 'Pendiente')
            ->count();

        // 2. Eventos para hoy (Creados por mí O aceptados por mí)
        $todaysEvents = Calendar::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereHas('participants', function ($q) use ($userId) {
                          $q->where('user_id', $userId)->where('status', 'Aceptado');
                      });
            })
            ->where(function ($q) use ($today, $endToday) {
                // Eventos que inician hoy o que están ocurriendo hoy (transcurso)
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
                    'start' => $event->start_time,
                    'end' => $event->end_time,
                    'creator' => $event->creator,
                    'participants' => $event->participants,
                    'my_status' => $status,
                    'is_creator' => $event->user_id === $userId,
                ];
            });

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'users' => User::where('id', '!=', $userId)->where('is_active', true)->get(),
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