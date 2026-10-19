<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionInput;
use App\Services\RentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayRentRequest extends FormRequest
{
    use TransactionInput;

    public function rules(): array
    {
        return [
            'amount' => $this->amountRules(),
            'payment_source' => ['required', Rule::in(RentService::SOURCES)],
        ] + $this->commonRules();
    }

    public function messages(): array
    {
        return $this->commonMessages() + [
            'payment_source.required' => 'Rent kahan se diya? Source chunein.',
        ];
    }
}
