<?php

namespace App\Actions\Payroll;

use App\Actions\Notifications\DispatchNotificationAction;
use App\Models\Incident;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Validation\ValidationException;

class ReviewVacationRequestAction
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotificationAction,
    ) {}

    /**
     * Approve or reject a pending vacation request. Approving it also creates
     * the vacation incident that feeds the attendance history.
     */
    public function execute(
        VacationRequest $vacationRequest,
        User $reviewer,
        bool $approve,
        ?string $notes = null,
    ): VacationRequest {
        if (! $vacationRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'La solicitud ya fue revisada.',
            ]);
        }

        $vacationRequest->update([
            'status' => $approve ? VacationRequest::STATUS_APPROVED : VacationRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        if ($approve) {
            Incident::create([
                'user_id' => $vacationRequest->user_id,
                'type' => Incident::TYPE_VACATION,
                'start_date' => $vacationRequest->start_date,
                'end_date' => $vacationRequest->end_date,
                'days' => $vacationRequest->days,
                'status' => Incident::STATUS_APPROVED,
                'vacation_request_id' => $vacationRequest->id,
                'created_by' => $reviewer->id,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
            ]);
        }

        $this->dispatchNotificationAction->vacationReviewed($vacationRequest);

        return $vacationRequest;
    }
}
