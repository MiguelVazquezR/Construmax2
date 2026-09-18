<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.categories.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre de la categoría.',
            'name.unique' => 'Ya existe una categoría con ese nombre.',
        ];
    }
}
