<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('daily_rent_suggest');
        if (is_string($value)) {
            $this->merge(['daily_rent_suggest' => str_replace([',', ' '], '', trim($value))]);
        }
    }

    public function rules(): array
    {
        return [
            'daily_rent_suggest' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'daily_rent_suggest.required' => 'Roz ka Rent Side amount likhein.',
        ];
    }
}
