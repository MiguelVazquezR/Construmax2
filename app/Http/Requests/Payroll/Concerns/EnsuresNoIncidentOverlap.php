<?php

namespace App\Http\Requests\Payroll\Concerns;

use App\Models\Incident;
use Illuminate\Validation\Validator;

/**
 * Shared rule of the incident forms: a collaborator cannot have two approved
 * incidents covering the same day (they would be counted twice).
 */
trait EnsuresNoIncidentOverlap
{
    protected function ensureNoIncidentOverlap(Validator $validator): void
    {
        // Basic rules failed first: let those errors surface alone.
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $start = $this->input('start_date');
        $end = $this->input('end_date') ?: $start;
        $userId = (int) $this->input('user_id');

        if (! $start || ! $userId) {
            return;
        }

        $overlaps = Incident::query()
            ->forUser($userId)
            ->approved()
            ->overlapping($start, $end)
            // Editing an incident must not collide with itself.
            ->when($this->route('incident'), fn ($query, $incident) => $query->whereKeyNot($incident->id))
            ->exists();

        if ($overlaps) {
            $validator->errors()->add('start_date', 'Ya existe una incidencia en uno de esos días para este colaborador.');
        }
    }

    /**
     * Custom messages shared by the incident forms.
     *
     * @return array<string, string>
     */
    protected function incidentMessages(): array
    {
        return [
            'user_id.required' => 'Selecciona al colaborador.',
            'user_id.exists' => 'El colaborador seleccionado no existe.',
            'type.required' => 'Selecciona el tipo de incidencia.',
            'start_date.required' => 'Indica la fecha de inicio.',
            'end_date.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'support.max' => 'El comprobante no debe exceder 5 MB.',
        ];
    }
}
