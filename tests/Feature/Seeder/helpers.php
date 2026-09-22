<?php

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

// Reaproveita `encerraTransacaoDoTeste`, `limpaBancoDeTeste` e `conferencia`:
// o cenário semeado precisa existir de verdade no banco e passar pelo mesmo
// comando de conferência que a aceitação da tarefa exige.
require_once __DIR__.'/../Reconciliation/helpers.php';

/** A pessoa de demonstração com este e-mail, como a aplicação a encontraria. */
function pessoaDemo(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

/** O saldo, em centavos, da carteira de quem tem este e-mail. */
function saldoDemo(string $email): int
{
    return (int) pessoaDemo($email)->wallet()->value('balance');
}

/**
 * As operações originais que terminaram desfeitas, por este motivo.
 *
 * O motivo mora no estorno, não na original: é o estorno que registra por que
 * foi feito. Por isso a pergunta passa pela ligação entre os dois.
 *
 * @return Collection<int, Transaction>
 */
function estornadasPor(string $motivo): Collection
{
    return Transaction::query()
        ->where('status', TransactionStatus::Reversed)
        ->whereHas('reversal', fn (Builder $estorno) => $estorno->where('reversal_reason', $motivo))
        ->get();
}

/**
 * O cenário reduzido ao que a demonstração mostra.
 *
 * Fica de fora tudo que muda a cada execução por natureza — identificadores,
 * chaves de idempotência e horários. O que sobra é exatamente o que precisa
 * sair igual toda vez: quem são as pessoas, quanto cada operação moveu, em que
 * estado ela ficou e onde o dinheiro parou.
 *
 * @return array<string, mixed>
 */
function retratoDaDemonstracao(): array
{
    return [
        'pessoas' => User::query()->orderBy('email')->pluck('name', 'email')->toArray(),
        'saldos' => User::query()->orderBy('email')->get()
            ->mapWithKeys(fn (User $user) => [$user->email => saldoDemo($user->email)])
            ->toArray(),
        'operacoes' => Transaction::query()->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (Transaction $transaction) => [
                'tipo' => $transaction->type->value,
                'estado' => $transaction->status->value,
                'valor' => $transaction->amount,
                'motivo' => $transaction->reversal_reason?->value,
            ])->toArray(),
        'lancamentos' => WalletEntry::query()->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (WalletEntry $entry) => [
                'tipo' => $entry->type->value,
                'valor' => $entry->amount,
                'saldo' => $entry->balance_after,
            ])->toArray(),
    ];
}
