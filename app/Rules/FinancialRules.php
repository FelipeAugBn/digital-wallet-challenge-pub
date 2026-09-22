<?php

namespace App\Rules;

/**
 * As regras que todo formulario financeiro repete, reunidas em um lugar so.
 *
 * Nao existe classe nova para `required` nem para `uuid`: aqui elas apenas sao
 * combinadas para que os Form Requests das proximas tarefas nao redigitem a
 * lista nem as mensagens.
 */
final class FinancialRules
{
    /**
     * O valor chega como texto do formulario e passa pelo conversor brasileiro.
     *
     * @return array<int, mixed>
     */
    public static function amount(): array
    {
        return ['required', 'string', new MoneyAmount];
    }

    /**
     * A chave que identifica a tentativa e evita a cobranca repetida.
     *
     * @return array<int, string>
     */
    public static function idempotencyKey(): array
    {
        return ['required', 'uuid'];
    }

    /**
     * Mensagens em portugues, sem citar detalhe interno para quem le a tela.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'amount.required' => 'Informe o valor.',
            'amount.string' => 'Informe o valor no formato 1.000,50.',
            'idempotency_key.required' => 'Não foi possível confirmar a operação. Recarregue a página e tente de novo.',
            'idempotency_key.uuid' => 'Não foi possível confirmar a operação. Recarregue a página e tente de novo.',
        ];
    }
}
