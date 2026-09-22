<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustStock', $this->route('product'));
    }

    public function rules(): array
    {
        return [
            // Relative change: +N to restock, -N to write off. Never absolute.
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => 'The quantity must not be zero.',
        ];
    }
}
