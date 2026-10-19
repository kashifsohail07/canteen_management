<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    use TransactionInput;

    public function rules(): array
    {
        return [
            'sale_segment_id' => ['required', 'integer', Rule::exists('sale_segments', 'id')->where('is_active', true)],
            'amount' => $this->amountRules(),
            'next' => ['nullable', 'boolean'],
        ] + $this->commonRules();
    }

    public function messages(): array
    {
        return $this->commonMessages() + [
            'sale_segment_id.required' => 'Sale ka segment chunein.',
        ];
    }
}
