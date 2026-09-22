<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            // Absolute stock writes would race with concurrent order decrements
            // (lost update). Stock changes go through the dedicated, row-locked
            // stock adjustment endpoint instead.
            'stock_quantity' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'stock_quantity.prohibited' => 'Stock cannot be set directly; use the stock adjustment endpoint.',
        ];
    }
}
