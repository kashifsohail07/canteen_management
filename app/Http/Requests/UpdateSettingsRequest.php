<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['monthly_rent_target', 'daily_rent_suggest'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $merge[$field] = str_replace([',', ' '], '', trim($value));
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'monthly_rent_target' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'daily_rent_suggest' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ];
    }
}
