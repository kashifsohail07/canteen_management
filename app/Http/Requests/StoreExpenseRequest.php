<?php

namespace App\Http\Requests;

use App\Enums\PaymentSource;
use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    use TransactionInput;

    public function rules(): array
    {
        return [
            'amount' => $this->amountRules(),
            'payment_source' => ['required', Rule::enum(PaymentSource::class)],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'new_category' => ['nullable', 'string', 'max:60'],
        ] + $this->commonRules();
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }
}
