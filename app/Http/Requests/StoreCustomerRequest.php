<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $opening = $this->input('opening_balance');
        $this->merge([
            'opening_balance' => is_string($opening) ? str_replace([',', ' '], '', trim($opening)) : $opening,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (Customer::whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])->exists()) {
                    $fail('Is naam ka customer pehle se maujood hai.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'balance_type' => ['nullable', Rule::in(['owed', 'advance'])],
        ];
    }

    /** Positive = customer owes us; negative = advance paid. */
    public function signedOpeningBalance(): string
    {
        $amount = (string) ($this->validated('opening_balance') ?? '0');
        if ($amount === '') {
            $amount = '0';
        }

        return $this->input('balance_type') === 'advance' ? '-'.ltrim($amount, '-') : $amount;
    }
}
