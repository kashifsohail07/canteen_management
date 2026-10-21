<?php

namespace App\Http\Requests;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor instanceof Vendor && ($this->user()?->can('update', $vendor) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $rate = $this->input('default_rate');
        $usesQty = $this->boolean('uses_quantity');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
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
        $vendor = $this->route('vendor');
        $usesQty = $this->boolean('uses_quantity');

        return [
            'name' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) use ($vendor) {
                $exists = Vendor::query()
                    ->where('id', '!=', $vendor->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->exists();
                if ($exists) {
                    $fail('Is naam ka vendor pehle se maujood hai.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'uses_quantity' => ['sometimes', 'boolean'],
            'default_item_name' => [$usesQty ? 'required' : 'nullable', 'string', 'max:120'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'default_rate' => [$usesQty ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,2'],
            'is_active' => ['boolean'],
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
}
