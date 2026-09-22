<?php

namespace App\Http\Requests;

use App\Rules\FinancialRules;
use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    /**
     * As regras financeiras vem prontas da T006, sem copia de formato ou limite.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => FinancialRules::amount(),
            'idempotency_key' => FinancialRules::idempotencyKey(),
        ];
    }

    /**
     * As mesmas mensagens que os proximos formularios financeiros vao usar.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return FinancialRules::messages();
    }
}
