<?php

namespace App\Http\Requests;

use App\Enums\PaymentSource;
use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    use TransactionInput;

    protected function moneyFields(): array
    {
        return ['bill_amount', 'paid_amount'];
    }

    public function rules(): array
    {
        $itemized = $this->input('entry_mode') === 'itemized';

        return [
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'entry_mode' => ['nullable', Rule::in(['total', 'itemized'])],
            'bill_amount' => $itemized ? ['nullable', 'numeric'] : $this->amountRules(),
            'paid_amount' => $this->amountRules(false, false),
            'payment_source' => ['nullable', Rule::enum(PaymentSource::class)],
            'bill_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'items' => [Rule::requiredIf($itemized), 'array'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'next' => ['nullable', 'boolean'],
        ] + $this->commonRules();
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $paid = (float) $this->input('paid_amount', 0);
                if ($paid > 0 && ! $this->input('payment_source')) {
                    $validator->errors()->add('payment_source', 'Choose payment source: canteen cash or personal pocket.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return $this->commonMessages() + [
            'vendor_id.required' => 'Please select a vendor.',
            'vendor_id.exists' => 'This vendor is missing or inactive.',
            'bill_amount.gt' => 'Bill amount must be greater than zero.',
            'bill_amount.required' => 'Enter the bill amount.',
            'paid_amount.min' => 'Paid amount cannot be negative.',
            'bill_image.image' => 'Upload a photo (JPG, PNG, or WebP).',
            'bill_image.max' => 'Photo must be under 10 MB.',
        ];
    }
}
