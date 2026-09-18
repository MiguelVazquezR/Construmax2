<?php

namespace App\Http\Requests\Expenses;

class UpdateExpenseRequest extends StoreExpenseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.edit');
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'remove_receipt_ids' => ['nullable', 'array'],
            'remove_receipt_ids.*' => ['integer'],
        ]);
    }
}
