<?php

namespace App\Services\Payroll;

use App\Models\AttendanceDayOverride;
use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\Incident;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceDayService
{
    public function __construct(
        private readonly ScheduleResolverService $scheduleResolver,
        private readonly HolidayService $holidayService,
    ) {}

    /**
     * Compute the attendance summary of a collaborator for a given day:
     * worked time, late arrival, early leave, overtime and status.
     *
     * The summary is calculated live from the punches, the resolved schedule
     * and the manual overrides; nothing but the overrides are persisted.
     */
    public function summaryFor(User $user, CarbonInterface|string $date): AttendanceDaySummary
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $schedule = $this->scheduleResolver->resolveFor($user, $day);
        $holiday = $this->holidayService->forDate($day);
        $incident = $this->incidentFor($user, $day);

        $punches = AttendanceLog::forUser($user->id)
            ->onDate($day->toDateString())
            ->orderBy('punched_at')
            ->get();

        $override = AttendanceDayOverride::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->first();

        [$workedMinutes, $pausedMinutes, $isWorking, $isPaused] = $this->calculateWorkedTime($punches, $schedule, $day);

        $firstIn = $punches->firstWhere('type', AttendanceLog::TYPE_CHECK_IN)?->punched_at;
        $lastOut = $punches->where('type', AttendanceLog::TYPE_CHECK_OUT)->last()?->punched_at;
        $lunchStart = $punches->firstWhere('type', AttendanceLog::TYPE_LUNCH_START)?->punched_at;
        $lunchEnd = $punches->where('type', AttendanceLog::TYPE_LUNCH_END)->last()?->punched_at;

        $isWorkday = $schedule?->isWorkday() ?? false;
        $expectedMinutes = ($schedule && $isWorkday && $holiday === null) ? $schedule->expectedDailyMinutes() : 0;

        [$lateMinutes, $earlyLeaveMinutes] = $holiday === null
            ? $this->calculateDeviations($schedule, $isWorkday, $firstIn, $lastOut)
            : [0, 0];

        $overtimeMinutes = ($isWorkday && $expectedMinutes > 0)
            ? max(0, $workedMinutes - $expectedMinutes)
            : 0;

        return new AttendanceDaySummary(
            date: $day,
            hasSchedule: $schedule !== null,
            isWorkday: $isWorkday,
            shift: $schedule?->shift,
            status: $this->resolveStatus($schedule, $isWorkday, $punches, $holiday, $incident),
            workedMinutes: $workedMinutes,
            pausedMinutes: $pausedMinutes,
            lateMinutes: $lateMinutes,
            lateIgnored: (bool) $override?->late_ignored,
            earlyLeaveMinutes: $earlyLeaveMinutes,
            overtimeMinutes: $overtimeMinutes,
            expectedMinutes: $expectedMinutes,
            firstIn: $firstIn,
            lastOut: $lastOut,
            lunchStart: $lunchStart,
            lunchEnd: $lunchEnd,
            isWorking: $isWorking,
            isPaused: $isPaused,
            punches: $punches,
            incidentType: $incident?->type_label,
            incidentTypeKey: $incident?->type,
            incidentPayPercentage: $incident?->payPercentage(),
            holidayName: $holiday?->name,
            notes: $override?->notes,
        );
    }

    /**
     * Approved incident covering the given day, when any.
     */
    private function incidentFor(User $user, CarbonImmutable $day): ?Incident
    {
        return Incident::query()
            ->forUser($user->id)
            ->approved()
            ->coveringDate($day)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Minutes worked through the day. Unpaid meal/break pauses and the meal
     * time are excluded; paid meals count as worked. Open intervals of the
     * current day are counted up to now.
     *
     * @param  Collection<int, AttendanceLog>  $punches
     * @return array{0: int, 1: int, 2: bool, 3: bool}
     */
    private function calculateWorkedTime(
        Collection $punches,
        ?ResolvedSchedule $schedule,
        CarbonImmutable $day,
    ): array {
        $mealIsPaid = $schedule?->mealIsPaid() ?? false;
        $workedSeconds = 0;
        $pausedSeconds = 0;
        $openSince = null;
        $pausedSince = null;
        $pausedIsMeal = false;

        foreach ($punches as $punch) {
            $at = $punch->punched_at;

            switch ($punch->type) {
                case AttendanceLog::TYPE_CHECK_IN:
                case AttendanceLog::TYPE_LUNCH_END:
                case AttendanceLog::TYPE_BREAK_END:
                    if ($pausedSince) {
                        $pausedSeconds += abs($at->diffInSeconds($pausedSince));

                        if ($pausedIsMeal && $mealIsPaid) {
                            $workedSeconds += abs($at->diffInSeconds($pausedSince));
                        }

                        $pausedSince = null;
                        $pausedIsMeal = false;
                    }

                    if (! $openSince) {
                        $openSince = $at;
                    }
                    break;

                case AttendanceLog::TYPE_LUNCH_START:
                case AttendanceLog::TYPE_BREAK_START:
                    if ($openSince) {
                        $workedSeconds += abs($at->diffInSeconds($openSince));
                        $openSince = null;
                    }

                    $pausedSince = $at;
                    $pausedIsMeal = $punch->type === AttendanceLog::TYPE_LUNCH_START;
                    break;

                case AttendanceLog::TYPE_CHECK_OUT:
                    if ($openSince) {
                        $workedSeconds += abs($at->diffInSeconds($openSince));
                        $openSince = null;
                    }

                    if ($pausedSince) {
                        $pausedSeconds += abs($at->diffInSeconds($pausedSince));

                        if ($pausedIsMeal && $mealIsPaid) {
                            $workedSeconds += abs($at->diffInSeconds($pausedSince));
                        }

                        $pausedSince = null;
                        $pausedIsMeal = false;
                    }
                    break;
            }
        }

        $isWorking = $openSince !== null;
        $isPaused = $pausedSince !== null;

        // Live day: count the interval that is still running.
        if ($day->isToday()) {
            $now = now();

            if ($openSince) {
                $workedSeconds += abs($now->diffInSeconds($openSince));
            } elseif ($pausedSince && $pausedIsMeal && $mealIsPaid) {
                $workedSeconds += abs($now->diffInSeconds($pausedSince));
            }
        }

        return [
            intdiv((int) $workedSeconds, 60),
            intdiv((int) $pausedSeconds, 60),
            $isWorking,
            $isPaused,
        ];
    }

    /**
     * Late arrival and early leave minutes against the expected schedule.
     *
     * @return array{0: int, 1: int}
     */
    private function calculateDeviations(
        ?ResolvedSchedule $schedule,
        bool $isWorkday,
        ?CarbonInterface $firstIn,
        ?CarbonInterface $lastOut,
    ): array {
        if (! $schedule || ! $isWorkday) {
            return [0, 0];
        }

        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;

        $expectedStart = $schedule->expectedStart();

        if ($expectedStart && $firstIn && $firstIn->greaterThan($expectedStart)) {
            $rawLate = (int) $expectedStart->diffInMinutes($firstIn);
            $tolerance = $schedule->lateToleranceMinutes(PayrollSetting::current()->late_tolerance_minutes);
            $lateMinutes = $rawLate > $tolerance ? $rawLate : 0;
        }

        $expectedEnd = $schedule->expectedEnd();

        if ($expectedEnd && $lastOut && $lastOut->lessThan($expectedEnd)) {
            $earlyLeaveMinutes = (int) $lastOut->diffInMinutes($expectedEnd);
        }

        return [$lateMinutes, $earlyLeaveMinutes];
    }

    /**
     * @param  Collection<int, AttendanceLog>  $punches
     */
    private function resolveStatus(
        ?ResolvedSchedule $schedule,
        bool $isWorkday,
        Collection $punches,
        ?Holiday $holiday,
        ?Incident $incident,
    ): string {
        if ($incident) {
            return AttendanceDaySummary::STATUS_INCIDENT;
        }

        if ($holiday) {
            return AttendanceDaySummary::STATUS_HOLIDAY;
        }

        if (! $schedule) {
            return AttendanceDaySummary::STATUS_NO_SCHEDULE;
        }

        if ($punches->isEmpty()) {
            return $isWorkday ? AttendanceDaySummary::STATUS_ABSENT : AttendanceDaySummary::STATUS_REST_DAY;
        }

        if (! $isWorkday) {
            return AttendanceDaySummary::STATUS_REST_DAY;
        }

        return AttendanceDaySummary::STATUS_PRESENT;
    }
}
