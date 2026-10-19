<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'bill_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'bill_image.required' => 'Bill ki photo chunein.',
            'bill_image.image' => 'Sirf photo (JPG, PNG, WebP) upload karein.',
            'bill_image.max' => 'Photo 10 MB se choti honi chahiye.',
        ];
    }
}
