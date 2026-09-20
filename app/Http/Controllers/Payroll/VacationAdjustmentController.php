<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreVacationAdjustmentRequest;
use App\Models\User;
use App\Models\VacationAdjustment;
use App\Services\Payroll\VacationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VacationAdjustmentController extends Controller
{
    public function __construct(
        private readonly VacationService $vacationService,
    ) {}

    /**
     * Register a manual movement on the vacation balance of a collaborator:
     * the initial balance, days granted by the company or a positive/negative
     * correction made by the payroll team.
     */
    public function store(StoreVacationAdjustmentRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $adjustment = VacationAdjustment::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'days' => round((float) $data['days'], 2),
            'reason' => $data['reason'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $balance = $this->vacationService->balanceFor($user);

        return back()->with('success', $this->savedMessage($adjustment, $user, $balance));
    }

    /**
     * Remove a manual movement. The balance is calculated live from the
     * movements, so deleting the row is enough to recalculate it.
     */
    public function destroy(Request $request, VacationAdjustment $adjustment): RedirectResponse
    {
        if (! $request->user()->can('payroll.vacations.manage')) {
            abort(403);
        }

        $name = $adjustment->user?->name ?? 'el colaborador';
        $adjustment->delete();

        return back()->with('success', 'Movimiento eliminado: el saldo de vacaciones de '.$name.' se recalculó.');
    }

    private function savedMessage(VacationAdjustment $adjustment, User $user, array $balance): string
    {
        $withSign = $adjustment->type === VacationAdjustment::TYPE_ADJUSTMENT;
        $days = $this->formatDays((float) $adjustment->days, $withSign);

        $message = match ($adjustment->type) {
            VacationAdjustment::TYPE_INITIAL => 'Saldo inicial de '.$days.' día(s) registrado para '.$user->name.'.',
            VacationAdjustment::TYPE_GRANT => 'Se agregaron '.$days.' día(s) al saldo de '.$user->name.'.',
            default => 'Ajuste de '.$days.' día(s) registrado para '.$user->name.'.',
        };

        return $message.' Ahora tiene '.$this->formatDays((float) $balance['available_days'], false).' día(s) disponibles.';
    }

    private function formatDays(float $days, bool $withSign = true): string
    {
        $formatted = rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');

        return $withSign && $days > 0 ? '+'.$formatted : $formatted;
    }
}
