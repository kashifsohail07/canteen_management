<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('opening_balance');
        $this->merge(['opening_balance' => is_string($value) ? str_replace([',', ' '], '', trim($value)) : $value]);
    }

    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'min:-999999999', 'max:999999999', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Opening balance badalne ki wajah likhna zaroori hai.'];
    }
}
