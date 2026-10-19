<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared behaviour for money-entry requests:
 *  - idempotency key from the X-Idempotency-Key header or the idempotency_key field
 *  - thousand separators / spaces are stripped from amount fields before validation
 *  - business_date (optional) for back-dated entries
 */
trait TransactionInput
{
    /** Request fields holding money values (normalised before validation). */
    protected function moneyFields(): array
    {
        return ['amount'];
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        $key = $this->header('X-Idempotency-Key') ?: $this->input('idempotency_key');
        $merge['idempotency_key'] = $key ? trim((string) $key) : null;

        foreach ($this->moneyFields() as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $merge[$field] = str_replace([',', ' '], '', trim($value));
            }
        }

        foreach (['notes', 'business_date'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        $this->merge($merge);
    }

    /** @return array<int, mixed> */
    protected function amountRules(bool $positive = true, bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'numeric',
            $positive ? 'gt:0' : 'min:0',
            'max:999999999',
            'decimal:0,2',
        ];
    }

    /** @return array<string, array<int, mixed>> */
    protected function commonRules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'business_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function commonMessages(): array
    {
        return [
            'amount.gt' => 'Amount zero se zyada hona chahiye.',
            'amount.numeric' => 'Amount sirf number hona chahiye.',
            'amount.decimal' => 'Amount mein sirf 2 decimal ho sakte hain.',
            'amount.max' => 'Amount bohat bara hai.',
            'business_date.before_or_equal' => 'Aanay wali date mein entry nahi ho sakti.',
        ];
    }
}
