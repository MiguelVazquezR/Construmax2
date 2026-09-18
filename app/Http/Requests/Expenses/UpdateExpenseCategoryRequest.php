<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends StoreExpenseCategoryRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')->ignore($this->route('category')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
