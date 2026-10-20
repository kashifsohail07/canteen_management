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
                    $validator->errors()->add('payment_source', 'Paisay kahan se diye? Canteen cash ya personal pocket chunein.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return $this->commonMessages() + [
            'vendor_id.required' => 'Vendor chunein.',
            'vendor_id.exists' => 'Yeh vendor maujood nahi ya inactive hai.',
            'bill_amount.gt' => 'Bill amount zero se zyada hona chahiye.',
            'bill_amount.required' => 'Bill amount likhein.',
            'paid_amount.min' => 'Paid amount negative nahi ho sakta.',
            'bill_image.image' => 'Sirf photo (JPG, PNG, WebP) upload karein.',
            'bill_image.max' => 'Photo 10 MB se choti honi chahiye.',
        ];
    }
}
