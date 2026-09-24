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
// E `valoresNaTela`, `diasNaTela` e os leitores dos gráficos, para ler o extrato
// e o painel semeados como a pessoa os vê.
require_once __DIR__.'/../Dashboard/helpers.php';

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
 * Ficam de fora identificadores e chaves de idempotência, que mudam a cada
 * execução por natureza. Os horários entram: o seeder posiciona o relógio em
 * cada operação, e com o relógio fixado pelo teste eles precisam sair iguais
 * toda vez, tanto quanto as pessoas, os valores, os estados e os saldos.
 *
 * @return array<string, mixed>
 */
function retratoDaDemonstracao(): array
{
    return [
        'pessoas' => User::query()->orderBy('email')->get()
            ->mapWithKeys(fn (User $user) => [$user->email => [
                'nome' => $user->name,
                'desde' => $user->created_at->format('Y-m-d H:i:s'),
                'carteiraDesde' => $user->wallet->created_at->format('Y-m-d H:i:s'),
            ]])->toArray(),
        'saldos' => User::query()->orderBy('email')->get()
            ->mapWithKeys(fn (User $user) => [$user->email => saldoDemo($user->email)])
            ->toArray(),
        'operacoes' => Transaction::query()->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (Transaction $transaction) => [
                'tipo' => $transaction->type->value,
                'estado' => $transaction->status->value,
                'valor' => $transaction->amount,
                'motivo' => $transaction->reversal_reason?->value,
                'quando' => $transaction->created_at->format('Y-m-d H:i:s'),
            ])->toArray(),
        'lancamentos' => WalletEntry::query()->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (WalletEntry $entry) => [
                'tipo' => $entry->type->value,
                'valor' => $entry->amount,
                'saldo' => $entry->balance_after,
                'quando' => $entry->created_at->format('Y-m-d H:i:s'),
            ])->toArray(),
    ];
}

/**
 * As páginas do extrato da pessoa, já reduzidas aos valores mostrados.
 *
 * Segue o link de "mais antigas" até ele acabar, como a pessoa faria: o
 * número de páginas sai da tela, e não de uma conta feita aqui.
 *
 * @return array<int, list<string>>
 */
function paginasDoExtrato(User $pessoa): array
{
    $paginas = [];

    for ($n = 1; ; $n++) {
        $html = test()->actingAs($pessoa)->get(route('statement', ['page' => $n]))->getContent();
        $paginas[$n] = valoresNaTela($html);

        if (! str_contains($html, 'rel="next"')) {
            return $paginas;
        }
    }
}
