<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use Illuminate\Validation\ValidationException;

/**
 * Register a kiosk punch identified with the collaborator's employee number.
 */
class RegisterEmployeeNumberPunchAction
{
    public function __construct(
        private readonly RegisterAttendancePunchAction $registerPunchAction,
    ) {}

    /**
     * @param  array{employee_number: string, type: string, latitude: float|string|null, longitude: float|string|null, location_accuracy?: float|string|null, photo?: string|null}  $data
     */
    public function execute(array $data, ?AttendanceDevice $device = null): AttendanceLog
    {
        $profile = $this->findProfileByEmployeeNumber($data['employee_number']);

        if (! $profile) {
            throw ValidationException::withMessages([
                'employee_number' => 'Número de empleado no encontrado. Verifícalo o pide ayuda al administrador.',
            ]);
        }

        return $this->registerPunchAction->execute($profile->user, [
            'type' => $data['type'],
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_EMPLOYEE_NUMBER,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_accuracy' => $data['location_accuracy'] ?? null,
            'photo' => $data['photo'] ?? null,
        ], $device);
    }

    /**
     * Find the collaborator whose employee number matches (case-insensitive).
     */
    private function findProfileByEmployeeNumber(string $employeeNumber): ?PayrollProfile
    {
        return PayrollProfile::query()
            ->whereRaw('LOWER(employee_number) = ?', [mb_strtolower(trim($employeeNumber))])
            ->with('user')
            ->get()
            ->first(fn (PayrollProfile $profile) => $profile->user !== null);
    }
}
