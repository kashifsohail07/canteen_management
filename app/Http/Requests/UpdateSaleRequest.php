<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->isSuperUser();
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('amount');
        if (is_string($value)) {
            $this->merge(['amount' => str_replace([',', ' '], '', trim($value))]);
        }

        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'sale_segment_id' => ['required', 'integer', Rule::exists('sale_segments', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'sale_segment_id.required' => 'Sale ka segment chunein.',
            'amount.gt' => 'Amount zero se zyada hona chahiye.',
            'reason.required' => 'Edit ki wajah likhna zaroori hai.',
            'reason.min' => 'Wajah kam az kam 3 characters ki honi chahiye.',
        ];
    }
}
