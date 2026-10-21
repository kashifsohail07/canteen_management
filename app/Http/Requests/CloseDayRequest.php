<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;

class CloseDayRequest extends FormRequest
{
    use TransactionInput;

    protected function moneyFields(): array
    {
        return ['actual_counted_cash'];
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $amounts = $this->input('segment_amounts', []);
        if (is_array($amounts)) {
            $clean = [];
            foreach ($amounts as $id => $raw) {
                if ($raw === null || $raw === '') {
                    $clean[$id] = '';
                    continue;
                }
                $clean[$id] = is_string($raw) ? str_replace([',', ' '], '', trim($raw)) : $raw;
            }
            $this->merge(['segment_amounts' => $clean]);
        }
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'actual_counted_cash' => $this->amountRules(false),
            'difference_reason' => ['nullable', 'string', 'max:500'],
            'segment_reasons' => ['nullable', 'array'],
            'segment_reasons.*' => ['nullable', 'string', 'max:500'],
            'segment_reason_other' => ['nullable', 'array'],
            'segment_reason_other.*' => ['nullable', 'string', 'max:500'],
            'segment_amounts' => ['nullable', 'array'],
            'segment_amounts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'rent_reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_counted_cash.required' => 'Ginti ka cash likhein.',
            'actual_counted_cash.numeric' => 'Cash sirf number hona chahiye.',
        ];
    }

    /**
     * @return array{
     *     segment_reasons: array<int|string, string>,
     *     segment_reason_other: array<int|string, string>,
     *     segment_amounts: array<int|string, string|int|float|null>,
     *     rent_reason: ?string
     * }
     */
    public function checklistInput(): array
    {
        return [
            'segment_reasons' => $this->validated('segment_reasons') ?? [],
            'segment_reason_other' => $this->validated('segment_reason_other') ?? [],
            'segment_amounts' => $this->validated('segment_amounts') ?? [],
            'rent_reason' => $this->validated('rent_reason'),
        ];
    }
}
