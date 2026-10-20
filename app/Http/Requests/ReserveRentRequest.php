<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;

class ReserveRentRequest extends FormRequest
{
    use TransactionInput;

    public function rules(): array
    {
        return ['amount' => $this->amountRules()] + $this->commonRules();
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }
}
