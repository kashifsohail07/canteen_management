<?php

namespace App\Http\Requests;

use App\Enums\PaymentSource;
use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    use TransactionInput;

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'amount' => $this->amountRules(),
            'payment_source' => ['required', Rule::enum(PaymentSource::class)],
        ] + $this->commonRules();
    }

    public function messages(): array
    {
        return $this->commonMessages() + [
            'vendor_id.required' => 'Vendor chunein.',
            'payment_source.required' => 'Paisay kahan se diye? Source chunein.',
        ];
    }
}
