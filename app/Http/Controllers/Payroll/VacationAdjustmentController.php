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
     * the initial balance, days granted by the company, historic taken days
     * or a positive/negative correction made by the payroll team.
     */
    public function store(StoreVacationAdjustmentRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $days = round((float) $data['days'], 2);

        // Historic taken days are captured as a positive amount and stored
        // as a discount.
        if ($data['type'] === VacationAdjustment::TYPE_TAKEN) {
            $days = -abs($days);
        }

        $adjustment = VacationAdjustment::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'days' => $days,
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
        $days = (float) $adjustment->days;

        $message = match ($adjustment->type) {
            VacationAdjustment::TYPE_INITIAL => 'Saldo inicial de '.$this->formatDays($days, false).' día(s) registrado para '.$user->name.'.',
            VacationAdjustment::TYPE_GRANT => 'Se sumaron '.$this->formatDays($days, false).' día(s) ganados al saldo de '.$user->name.'.',
            VacationAdjustment::TYPE_TAKEN => 'Se registraron '.$this->formatDays(abs($days), false).' día(s) tomados para '.$user->name.'.',
            default => 'Ajuste de '.$this->formatDays($days, true).' día(s) registrado para '.$user->name.'.',
        };

        return $message.' Ahora tiene '.$this->formatDays((float) $balance['available_days'], false).' día(s) disponibles.';
    }

    private function formatDays(float $days, bool $withSign = true): string
    {
        $formatted = rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');

        return $withSign && $days > 0 ? '+'.$formatted : $formatted;
    }
}
