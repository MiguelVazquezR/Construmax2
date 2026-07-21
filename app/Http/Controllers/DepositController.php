<?php

namespace App\Http\Controllers;

use App\Actions\Deposits\ApproveDepositAction;
use App\Actions\Notifications\DispatchNotificationAction;
use App\Http\Requests\Deposits\StoreDepositRequest;
use App\Http\Requests\Deposits\UpdateDepositRequest;
use App\Models\Deposit;
use App\Models\DepositType;
use App\Models\Technician;
use App\Services\Deposits\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $depositService,
        private readonly DispatchNotificationAction $dispatchNotification,
        private readonly ApproveDepositAction $approveDepositAction,
    ) {}

    /**
     * List view with table + calendar tabs.
     */
    public function index(Request $request): Response
    {
        $query = Deposit::with([
            'technician.user',
            'bankAccount',
            'ticket',
            'depositType',
            'approvedBy',
        ])->latest();

        if ($request->filled('technician_id')) {
            $query->where('technician_id', $request->technician_id);
        }

        // Status filter: only apply when explicitly set to a non-empty value
        if ($request->has('status')) {
            if ($request->status !== '' && $request->status !== null) {
                $query->filter(['status' => $request->status]);
            }
            // If status param is present but empty/null → show all, no status filter
        }
        // If no status param at all → show all, no default filter

        // Shift filter: only apply when explicitly set to a non-empty value
        if ($request->has('shift')) {
            if ($request->shift !== '' && $request->shift !== null) {
                $query->filter(['shift' => $request->shift]);
            }
            // If shift param is present but empty/null → show all, no shift filter
        }

        $deposits = $query->paginate(20)->withQueryString();

        // Calendar events: group by scheduled_date
        $calendarEvents = Deposit::with('technician.user', 'depositType')
            ->get()
            ->groupBy(fn (Deposit $d) => $d->scheduled_date->format('Y-m-d'))
            ->map(fn ($items) => $items->values())
            ->toArray();

        return Inertia::render('Deposits/Index', [
            'deposits'       => $deposits,
            'calendarEvents' => $calendarEvents,
            'depositTypes'   => DepositType::active()->orderBy('name')->get(),
            'can'            => [
                'approve'      => $request->user()->can('deposits.approve'),
                'create'       => $request->user()->can('deposits.create'),
                'edit'         => $request->user()->can('deposits.edit'),
                'delete'       => $request->user()->can('deposits.delete'),
                'manageTypes'  => $request->user()->can('deposits.types.manage'),
                'viewTickets'  => $request->user()->can('tickets.index'),
            ],
            'defaultShift'   => $this->depositService->defaultShift(),
            'filters'        => [
                'technician_id' => $request->input('technician_id', ''),
                'status'        => $request->input('status', ''),
                'shift'         => $request->input('shift', ''),
            ],
        ]);
    }

    /**
     * Store a new deposit.
     */
    public function store(StoreDepositRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['budget_id'] = \App\Models\Ticket::find($data['ticket_id'])->budget->id;

        $deposit = Deposit::create($data);

        // Dispatch approval notification
        $this->dispatchNotification->depositPendingApproval($deposit);

        return redirect()->route('deposits.index')
            ->with('success', 'Depósito programado correctamente.');
    }

    /**
     * Update an existing deposit (only if not completed).
     */
    public function update(UpdateDepositRequest $request, Deposit $deposit): RedirectResponse
    {
        if ($deposit->status === 'completed') {
            return redirect()->route('deposits.index')
                ->with('error', 'No se puede editar un depósito completado.');
        }

        $data = $request->validated();
        $data['budget_id'] = \App\Models\Ticket::find($data['ticket_id'])->budget->id;

        $deposit->update($data);

        return redirect()->route('deposits.index')
            ->with('success', 'Depósito actualizado correctamente.');
    }

    /**
     * Delete a deposit (only if not completed).
     */
    public function destroy(Deposit $deposit): RedirectResponse
    {
        if ($deposit->status === 'completed') {
            return redirect()->route('deposits.index')
                ->with('error', 'No se puede eliminar un depósito completado.');
        }

        $deposit->delete();

        return redirect()->route('deposits.index')
            ->with('success', 'Depósito eliminado correctamente.');
    }

    /**
     * Approve a pending deposit.
     */
    public function approve(Request $request, Deposit $deposit): RedirectResponse
    {
        if (! $request->user()->can('deposits.approve')) {
            abort(403);
        }

        if ($deposit->status !== 'pending') {
            return redirect()->route('deposits.index')
                ->with('error', 'Solo los depósitos pendientes pueden ser aprobados.');
        }

        $this->approveDepositAction->execute($deposit, $request->user());

        return redirect()->route('deposits.index')
            ->with('success', 'Depósito aprobado correctamente.');
    }

    /**
     * Generate a permanent signed URL for a single deposit.
     */
    public function depositLink(Deposit $deposit): JsonResponse
    {
        return response()->json([
            'url' => URL::signedRoute('public.deposits.show', ['deposit' => $deposit->id]),
        ]);
    }

    /**
     * Generate a permanent signed URL for all deposits on a given day.
     */
    public function dayLink(Request $request, string $date): JsonResponse
    {
        return response()->json([
            'url' => URL::signedRoute('public.deposits.day', ['date' => $date]),
        ]);
    }

    /**
     * Get bank accounts for a technician (for the form selector).
     */
    public function technicianBankAccounts(Technician $technician): JsonResponse
    {
        $accounts = $technician->bankAccounts()
            ->orderByDesc('is_favorite')
            ->orderBy('id')
            ->get();

        return response()->json($accounts);
    }

    /**
     * Get pending tickets for a technician (for the form selector).
     */
    public function technicianPendingTickets(Technician $technician): JsonResponse
    {
        $tickets = $this->depositService->pendingTicketsForTechnician($technician)
            ->map(function ($ticket) use ($technician) {
                return [
                    'id'              => $ticket->id,
                    'folio'           => $ticket->folio,
                    'name'            => $ticket->name,
                    'customer_name'   => $ticket->customer->name ?? 'N/A',
                    'budget_id'       => $ticket->budget->id,
                    'pending_amount'  => $this->depositService->pendingAmountForTechnician($technician, $ticket->budget),
                ];
            });

        return response()->json($tickets);
    }
}
