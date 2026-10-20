<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionInput;
use Illuminate\Foundation\Http\FormRequest;

class SkipRentRequest extends FormRequest
{
    use TransactionInput;

    public function rules(): array
    {
        return $this->commonRules();
    }
}
