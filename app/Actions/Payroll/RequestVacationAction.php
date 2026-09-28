<?php

namespace App\Actions\Payroll;

use App\Actions\Notifications\DispatchNotificationAction;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Payroll\VacationService;

class RequestVacationAction
{
    public function __construct(
        private readonly VacationService $vacationService,
        private readonly DispatchNotificationAction $dispatchNotificationAction,
    ) {}

    /**
     * Register a vacation request after validating the balance, the configured
     * minimum and overlapping requests. Returns the pending request.
     */
    public function execute(User $user, array $data, ?User $actor = null): VacationRequest
    {
        $days = $this->vacationService->validateRequest(
            $user,
            $data['start_date'],
            $data['end_date'],
        );

        $request = VacationRequest::create([
            'user_id' => $user->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'status' => VacationRequest::STATUS_PENDING,
            'reason' => $data['reason'] ?? null,
            'requested_by' => $actor?->id ?? $user->id,
        ]);

        $this->dispatchNotificationAction->vacationRequested($request);

        return $request;
    }
}
