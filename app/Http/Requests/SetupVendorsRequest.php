<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetupVendorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('vendors', []))
            ->map(function ($row) {
                $row = is_array($row) ? $row : [];
                if (isset($row['opening_balance']) && is_string($row['opening_balance'])) {
                    $row['opening_balance'] = str_replace([',', ' '], '', trim($row['opening_balance']));
                }
                $row['name'] = isset($row['name']) ? trim((string) $row['name']) : '';

                return $row;
            })
            ->filter(fn ($row) => $row['name'] !== '' || ! empty($row['opening_balance']))
            ->values()
            ->all();

        $this->merge(['vendors' => $rows]);
    }

    public function rules(): array
    {
        return [
            'vendors' => ['required', 'array', 'min:1', 'max:60'],
            'vendors.*.name' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'vendors.*.phone' => ['nullable', 'string', 'max:30'],
            'vendors.*.opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'vendors.*.balance_type' => ['nullable', Rule::in(['owed', 'advance'])],
        ];
    }

    public function messages(): array
    {
        return [
            'vendors.required' => 'Kam az kam ek vendor ka naam likhein.',
            'vendors.min' => 'Kam az kam ek vendor ka naam likhein.',
            'vendors.*.name.required' => 'Vendor ka naam likhein.',
            'vendors.*.name.distinct' => 'Yeh naam list mein dobara aaya hai.',
        ];
    }
}
