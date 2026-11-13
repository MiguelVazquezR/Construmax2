<?php

namespace App\Services\Payroll;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\Payroll\Exceptions\AttendancePunchNotAllowedException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Daily attendance sequence of a collaborator.
 *
 * Entry and exit are required, while lunch and permission cycles are optional
 * and may happen more than once. The state is rebuilt from the punches of the
 * requested day only, so a day that was never closed does not affect the next
 * day: every day starts again with the entry.
 */
class AttendancePunchPolicy
{
    public const STATE_OUTSIDE = 'outside';

    public const STATE_WORKING = 'working';

    public const STATE_MEAL = 'meal';

    public const STATE_BREAK = 'break';

    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Every punch type with whether it can be registered now.
     *
     * @return array<int, array{
     *     type: string,
     *     label: string,
     *     allowed: bool,
     *     reason: string|null,
     *     last_registered_at: string|null
     * }>
     */
    public function optionsFor(User $user, Carbon|string|null $date = null): array
    {
        $punches = $this->attendanceService->punchesFor($user, $date);
        $state = $this->stateFrom($punches);
        $lastByType = $punches->groupBy('type')->map->last();

        $options = [];

        foreach (AttendanceLog::TYPES as $type => $label) {
            $allowed = $this->isAllowed($type, $state);
            $lastPunch = $lastByType->get($type);

            $options[] = [
                'type' => $type,
                'label' => $label,
                'allowed' => $allowed,
                'reason' => $allowed ? null : $this->reasonFor($type, $state, $lastByType),
                'last_registered_at' => $lastPunch?->punched_at?->format('H:i'),
            ];
        }

        return $options;
    }

    /**
     * Throw when the requested type does not follow the daily sequence.
     *
     * @throws AttendancePunchNotAllowedException
     */
    public function ensureCanRegister(User $user, string $type, Carbon|string|null $date = null): void
    {
        $options = collect($this->optionsFor($user, $date))->keyBy('type');
        $option = $options->get($type);

        if (! $option || $option['allowed']) {
            return;
        }

        throw new AttendancePunchNotAllowedException(
            $type,
            $option['reason'] ?? 'Este registro no está permitido en este momento.',
            $option['last_registered_at'],
            $options->values()->all(),
        );
    }

    public function stateFor(User $user, Carbon|string|null $date = null): string
    {
        return $this->stateFrom($this->attendanceService->punchesFor($user, $date));
    }

    private function isAllowed(string $type, string $state): bool
    {
        return match ($type) {
            AttendanceLog::TYPE_CHECK_IN => $state === self::STATE_OUTSIDE,
            AttendanceLog::TYPE_CHECK_OUT => in_array($state, [
                self::STATE_WORKING,
                self::STATE_MEAL,
                self::STATE_BREAK,
            ], true),
            AttendanceLog::TYPE_LUNCH_START,
            AttendanceLog::TYPE_BREAK_START => $state === self::STATE_WORKING,
            AttendanceLog::TYPE_LUNCH_END => $state === self::STATE_MEAL,
            AttendanceLog::TYPE_BREAK_END => $state === self::STATE_BREAK,
            default => false,
        };
    }

    /**
     * @param  Collection<int, AttendanceLog>  $punches  Ordered chronologically.
     */
    private function stateFrom(Collection $punches): string
    {
        $state = self::STATE_OUTSIDE;

        foreach ($punches->sortBy('punched_at') as $punch) {
            $state = match ($punch->type) {
                AttendanceLog::TYPE_CHECK_IN => self::STATE_WORKING,
                AttendanceLog::TYPE_LUNCH_START => $state === self::STATE_WORKING
                    ? self::STATE_MEAL
                    : $state,
                AttendanceLog::TYPE_LUNCH_END => $state === self::STATE_MEAL
                    ? self::STATE_WORKING
                    : $state,
                AttendanceLog::TYPE_BREAK_START => $state === self::STATE_WORKING
                    ? self::STATE_BREAK
                    : $state,
                AttendanceLog::TYPE_BREAK_END => $state === self::STATE_BREAK
                    ? self::STATE_WORKING
                    : $state,
                AttendanceLog::TYPE_CHECK_OUT => self::STATE_OUTSIDE,
                default => $state,
            };
        }

        return $state;
    }

    /**
     * @param  Collection<string, AttendanceLog>  $lastByType
     */
    private function reasonFor(string $type, string $state, Collection $lastByType): string
    {
        $lastPunch = $lastByType->get($type);
        $time = $lastPunch?->punched_at?->format('H:i');

        return match ($type) {
            AttendanceLog::TYPE_CHECK_IN => $time !== null
                ? "Ya registraste tu entrada a las {$time}. Selecciona otro tipo de registro."
                : 'Primero registra tu entrada.',

            AttendanceLog::TYPE_CHECK_OUT => match (true) {
                $state === self::STATE_OUTSIDE && $time !== null => "Ya registraste tu salida a las {$time}. Registra una nueva entrada si vas a continuar.",
                $state === self::STATE_OUTSIDE => 'Primero registra tu entrada.',
                default => 'No puedes registrar la salida en este momento.',
            },

            AttendanceLog::TYPE_LUNCH_START => match ($state) {
                self::STATE_MEAL => $time !== null
                    ? "Ya registraste el inicio de comida a las {$time}. Registra el fin de comida."
                    : 'Ya tienes una comida en curso.',
                self::STATE_BREAK => 'Primero registra tu regreso de permiso.',
                self::STATE_OUTSIDE => 'Primero registra tu entrada.',
                default => 'No puedes iniciar la comida en este momento.',
            },

            AttendanceLog::TYPE_LUNCH_END => match (true) {
                $state === self::STATE_WORKING && $time !== null => "Ya registraste el fin de comida a las {$time}.",
                $state === self::STATE_OUTSIDE => 'Primero registra tu entrada.',
                default => 'No tienes una comida en curso.',
            },

            AttendanceLog::TYPE_BREAK_START => match ($state) {
                self::STATE_BREAK => $time !== null
                    ? "Ya registraste un permiso a las {$time}. Registra tu regreso de permiso."
                    : 'Ya tienes un permiso en curso.',
                self::STATE_MEAL => 'Termina tu comida antes de registrar un permiso.',
                self::STATE_OUTSIDE => 'Primero registra tu entrada.',
                default => 'No puedes registrar un permiso en este momento.',
            },

            AttendanceLog::TYPE_BREAK_END => match (true) {
                $state === self::STATE_WORKING && $time !== null => "Ya registraste tu regreso de permiso a las {$time}.",
                $state === self::STATE_OUTSIDE => 'Primero registra tu entrada.',
                default => 'No tienes un permiso en curso.',
            },

            default => 'Este registro no está permitido en este momento.',
        };
    }
}
