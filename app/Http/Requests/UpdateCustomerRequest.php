<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && ($this->user()?->can('update', $customer) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) use ($customer) {
                $exists = Customer::query()
                    ->where('id', '!=', $customer->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->exists();
                if ($exists) {
                    $fail('Is naam ka customer pehle se maujood hai.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
