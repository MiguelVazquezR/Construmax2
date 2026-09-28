<?php

namespace App\Services\Payroll;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Punches of a collaborator for a given date (today by default), ordered chronologically.
     *
     * @return Collection<int, AttendanceLog>
     */
    public function punchesFor(User $user, Carbon|string|null $date = null): Collection
    {
        $date = $date ? Carbon::parse($date) : today();

        return AttendanceLog::forUser($user->id)
            ->onDate($date->toDateString())
            ->orderBy('punched_at')
            ->get();
    }

    /**
     * Next punch type suggested by the punches already registered today.
     */
    public function suggestedTypeFor(User $user, Carbon|string|null $date = null): string
    {
        return AttendanceLog::nextSuggestedType($this->punchesFor($user, $date));
    }
}
