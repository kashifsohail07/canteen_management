<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequiredItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\RequiredItem::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'class_name' => ['required', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
            'quantity_requested' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'unit' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Item name is required.',
            'class_name.required' => 'Please select a class.',
        ];
    }
}
