<?php

namespace App\Http\Requests;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vendor::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $opening = $this->input('opening_balance');
        $rate = $this->input('default_rate');
        $usesQty = $this->boolean('uses_quantity');

        $this->merge([
            'opening_balance' => is_string($opening) ? str_replace([',', ' '], '', trim($opening)) : $opening,
            'uses_quantity' => $usesQty,
            'default_rate' => is_string($rate) ? str_replace([',', ' '], '', trim($rate)) : $rate,
            'default_item_name' => $this->filled('default_item_name') ? trim((string) $this->input('default_item_name')) : null,
            'default_unit' => $usesQty ? 'pcs' : null,
        ]);

        if (! $usesQty || $this->input('default_rate') === '') {
            $this->merge([
                'default_rate' => null,
                'default_item_name' => $usesQty ? $this->input('default_item_name') : null,
                'default_unit' => null,
            ]);
        }
    }

    public function rules(): array
    {
        $usesQty = $this->boolean('uses_quantity');

        return [
            'name' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (Vendor::whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])->exists()) {
                    $fail('Is naam ka vendor pehle se maujood hai.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'balance_type' => ['nullable', Rule::in(['owed', 'advance'])],
            'uses_quantity' => ['sometimes', 'boolean'],
            'default_item_name' => [$usesQty ? 'required' : 'nullable', 'string', 'max:120'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'default_rate' => [$usesQty ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_rate.required' => 'Quantity wala vendor ke liye per pcs price likho.',
            'default_rate.gt' => 'Per pcs price zero se zyada hona chahiye.',
            'default_item_name.required' => 'Item name likho (e.g. Samosay).',
        ];
    }

    /** Opening balance as a signed number: positive = we owe vendor, negative = advance paid. */
    public function signedOpeningBalance(): string
    {
        $amount = (string) ($this->validated('opening_balance') ?? '0');
        if ($amount === '') {
            $amount = '0';
        }

        return $this->input('balance_type') === 'advance' ? '-'.ltrim($amount, '-') : $amount;
    }
}
