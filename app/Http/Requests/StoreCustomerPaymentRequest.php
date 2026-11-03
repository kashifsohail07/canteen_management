<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');
        $this->merge([
            'amount' => is_string($amount) ? str_replace([',', ' '], '', trim($amount)) : $amount,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:500'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer chunein.',
            'amount.gt' => 'Amount zero se zyada hona chahiye.',
        ];
    }
}
