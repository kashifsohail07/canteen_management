<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;

class CloseDayRequest extends FormRequest
{
    use TransactionInput;

    protected function moneyFields(): array
    {
        return ['actual_counted_cash'];
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'actual_counted_cash' => $this->amountRules(false),
            'difference_reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_counted_cash.required' => 'Ginti ka cash likhein.',
            'actual_counted_cash.numeric' => 'Cash sirf number hona chahiye.',
        ];
    }
}
