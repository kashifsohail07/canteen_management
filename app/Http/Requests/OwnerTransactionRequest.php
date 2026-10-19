<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;

/** Used for both owner deposit and owner repayment. */
class OwnerTransactionRequest extends FormRequest
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
