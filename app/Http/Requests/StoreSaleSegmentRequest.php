<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Segment ka naam likhein.',
        ];
    }
}
