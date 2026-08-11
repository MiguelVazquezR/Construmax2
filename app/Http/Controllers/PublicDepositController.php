<?php

namespace App\Http\Controllers;

use App\Actions\Deposits\CompleteDepositAction;
use App\Http\Requests\Deposits\CompleteDepositRequest;
use App\Models\Deposit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class PublicDepositController extends Controller
{
    public function __construct(
        private readonly CompleteDepositAction $completeDepositAction,
    ) {}

    /**
     * Public view for a single deposit.
     * Shows full bank details if approved/completed, limited info if pending.
     */
    public function show(Request $request, Deposit $deposit): Response
    {
        $deposit->load([
            'technician.user',
            'bankAccount.media',
            'ticket',
            'depositType',
            'approvedBy',
        ]);

        $bankQrUrl = null;
        if ($deposit->bankAccount) {
            $qrMedia = $deposit->bankAccount->getFirstMedia('bank_qr');
            $bankQrUrl = $qrMedia?->getUrl();
        }

        return Inertia::render('Public/Deposits/Show', [
            'deposit'      => $deposit,
            'completeUrl'  => URL::signedRoute('public.deposits.complete', ['deposit' => $deposit->id]),
            'bankQrUrl'    => $bankQrUrl,
        ]);
    }

    /**
     * Public view for all deposits on a given day, grouped by shift.
     *
     * TODO: Once the client confirms real shift hours, add automatic filtering
     * to show only the current shift's deposits based on the time of day.
     * For now, both shifts are shown with clear labels.
     */
    public function day(Request $request, string $date): Response
    {
        $deposits = Deposit::with([
            'technician.user',
            'bankAccount.media',
            'ticket',
            'depositType',
            'approvedBy',
        ])
            ->whereDate('scheduled_date', $date)
            ->get()
            ->groupBy('shift')
            ->map(function ($group) {
                return $group->map(function ($deposit) {
                    $deposit->complete_url = URL::signedRoute('public.deposits.complete', ['deposit' => $deposit->id]);
                    return $deposit;
                });
            });

        return Inertia::render('Public/Deposits/Day', [
            'deposits' => $deposits,
            'date'     => $date,
        ]);
    }

    /**
     * Mark a deposit as completed (public route, no auth).
     */
    public function complete(CompleteDepositRequest $request, Deposit $deposit): RedirectResponse
    {
        if ($deposit->status === 'completed') {
            return back()->with('error', 'Este depósito ya fue marcado como realizado.');
        }

        if ($deposit->status !== 'approved') {
            return back()->with('error', 'Solo los depósitos aprobados pueden marcarse como realizados.');
        }

        $this->completeDepositAction->execute($deposit, $request->validated());

        return back()->with('success', 'Depósito marcado como realizado. El pago se registró automáticamente.');
    }
}
