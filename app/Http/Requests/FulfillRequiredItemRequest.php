<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FulfillRequiredItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('requiredItem');

        return $this->user()?->can('fulfill', $item) ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity_received' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity_received.required' => 'Received quantity is required.',
            'quantity_received.gt' => 'Received quantity must be greater than zero.',
            'price.required' => 'Price is required.',
        ];
    }
}
