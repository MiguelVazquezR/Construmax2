<?php

namespace App\Http\Requests\Payroll;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.shifts.manage');
    }

    /**
     * Per-day shifts build their schedule day by day: the working days are
     * derived from the days configured with a start and end time.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('type') !== Shift::TYPE_PER_DAY) {
            return;
        }

        $schedules = Collection::make($this->input('day_schedules', []))
            ->mapWithKeys(function (mixed $schedule, int|string $day): array {
                $day = (int) $day;

                if ($day < 1 || $day > 7 || ! is_array($schedule)) {
                    return [];
                }

                $start = $schedule['start_time'] ?? null;
                $end = $schedule['end_time'] ?? null;

                if (blank($start) || blank($end)) {
                    return [];
                }

                return [$day => [
                    'start_time' => $start,
                    'end_time' => $end,
                    'meal_minutes' => (int) ($schedule['meal_minutes'] ?? 0),
                ]];
            })
            ->sortKeys();

        $this->merge([
            'day_schedules' => $schedules->all(),
            'days' => $schedules->keys()->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(Shift::TYPES))],

            'start_time' => ['nullable', 'required_if:type,fixed', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'required_if:type,fixed', 'date_format:H:i,H:i:s'],
            'meal_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'is_meal_paid' => ['sometimes', 'boolean'],

            'days' => ['nullable', 'array', 'min:1', 'required_unless:type,per_day'],
            'days.*' => ['integer', 'between:1,7'],

            'day_schedules' => ['nullable', 'array', 'required_if:type,per_day'],
            'day_schedules.*' => ['array'],
            'day_schedules.*.start_time' => ['required', 'date_format:H:i,H:i:s'],
            'day_schedules.*.end_time' => ['required', 'date_format:H:i,H:i:s'],
            'day_schedules.*.meal_minutes' => ['required', 'integer', 'min:0', 'max:480'],

            'required_daily_hours' => ['nullable', 'required_if:type,flexible', 'numeric', 'min:0.5', 'max:24'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del turno es obligatorio.',
            'start_time.required_if' => 'Indica la hora de entrada del turno fijo.',
            'end_time.required_if' => 'Indica la hora de salida del turno fijo.',
            'days.required_unless' => 'Selecciona al menos un día de la semana.',
            'days.min' => 'Selecciona al menos un día de la semana.',
            'day_schedules.required_if' => 'Configura al menos un día de trabajo con su horario.',
            'required_daily_hours.required_if' => 'Indica las horas diarias requeridas del turno flexible.',
        ];
    }
}
