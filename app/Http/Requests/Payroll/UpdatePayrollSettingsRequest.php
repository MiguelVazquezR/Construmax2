<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.settings.manage');
    }

    public function rules(): array
    {
        return [
            // Face recognition
            'face_recognition_enabled' => ['required', 'boolean'],
            'face_match_threshold' => ['required', 'integer', 'min:50', 'max:100'],
            'kiosk_pin_fallback_enabled' => ['required', 'boolean'],
            'rekognition_collection_id' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.\-]+$/'],

            // Payroll period: weekly Monday–Sunday, opened and closed by the
            // system itself (period_type and period_anchor_date are legacy
            // columns and no longer part of the form)

            // Late arrivals
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'late_discount_mode' => ['required', 'in:track_only,deduct_minutes'],

            // Overtime (paid at the normal rate: the multiplier and threshold
            // columns are legacy and no longer part of the form)
            'overtime_double_multiplier' => ['sometimes', 'numeric', 'min:1', 'max:10'],
            'overtime_triple_multiplier' => ['sometimes', 'numeric', 'min:1', 'max:10'],
            'overtime_weekly_threshold_hours' => ['sometimes', 'numeric', 'min:0', 'max:48'],

            // Worked holidays
            'holiday_worked_extra_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],

            // Vacations
            'vacation_min_days_to_request' => ['required', 'numeric', 'min:0', 'max:60'],
            'vacation_carryover_months' => ['required', 'integer', 'min:0', 'max:60'],
            'vacation_premium_notice_enabled' => ['required', 'boolean'],
            'vacation_premium_notice_mode' => ['required', 'in:once,daily'],

            // Medical leaves (both incapacity types are covered by the IMSS;
            // the columns are legacy and no longer part of the form)
            'incapacity_paid' => ['sometimes', 'boolean'],
            'incapacity_pay_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],

            // Defaults (the default daily hours column is legacy: the hours
            // follow the schedule assigned in the user/technician forms)
            'default_daily_hours' => ['sometimes', 'numeric', 'min:1', 'max:24'],
            'payroll_expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],

            // Attendance evidence
            'attendance_capture_retention_months' => ['required', 'integer', 'min:1', 'max:120'],
            'remote_geolocation_required' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'face_match_threshold.min' => 'El umbral de coincidencia debe ser de al menos 50.',
            'face_match_threshold.max' => 'El umbral de coincidencia no puede superar 100.',
            'rekognition_collection_id.regex' => 'La colección solo admite letras, números, guiones, puntos y guiones bajos.',
            'late_discount_mode.in' => 'El modo de descuento de retardos no es válido.',
            'payroll_expense_category_id.exists' => 'La categoría de gasto seleccionada no existe.',
        ];
    }
}
