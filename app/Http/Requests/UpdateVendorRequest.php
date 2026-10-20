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
        $this->merge(['is_active' => $this->boolean('is_active')]);
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
            'is_active' => ['boolean'],
        ];
    }
}
