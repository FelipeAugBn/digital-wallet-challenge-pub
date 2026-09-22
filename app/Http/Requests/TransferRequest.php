<?php

namespace App\Http\Requests;

use App\Rules\FinancialRules;
use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    /**
     * Valor e chave vem prontos da T006; aqui entra apenas o destinatario.
     *
     * A existencia do e-mail nao e checada por regra de validacao: quem
     * resolve o destinatario e a Action, que tambem precisa recusar a
     * transferencia para a propria carteira.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'string', 'email'],
            'amount' => FinancialRules::amount(),
            'idempotency_key' => FinancialRules::idempotencyKey(),
        ];
    }

    /**
     * As mensagens financeiras compartilhadas mais as do campo de e-mail.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(FinancialRules::messages(), [
            'recipient_email.required' => 'Informe o e-mail de quem vai receber.',
            'recipient_email.string' => 'Informe um e-mail válido.',
            'recipient_email.email' => 'Informe um e-mail válido.',
        ]);
    }
}
