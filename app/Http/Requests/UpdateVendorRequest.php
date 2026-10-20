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
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'default_rate' => is_string($rate) ? str_replace([',', ' '], '', trim($rate)) : $rate,
            'default_item_name' => $this->filled('default_item_name') ? trim((string) $this->input('default_item_name')) : null,
            'default_unit' => $this->filled('default_unit') ? trim((string) $this->input('default_unit')) : null,
        ]);

        if ($this->input('default_rate') === '') {
            $this->merge(['default_rate' => null]);
        }
    }

    public function rules(): array
    {
        $vendor = $this->route('vendor');

        return [
            'name' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) use ($vendor) {
                $exists = Vendor::withTrashed()
                    ->where('id', '!=', $vendor->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->exists();
                if ($exists) {
                    $fail('Is naam ka vendor pehle se maujood hai.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'default_item_name' => ['nullable', 'string', 'max:120'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'default_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'is_active' => ['boolean'],
        ];
    }
}
