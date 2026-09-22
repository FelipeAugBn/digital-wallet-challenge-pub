<?php

use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Reaproveita `userWithWallet`, `walletOf`, `deposit`, `transfer` e `reverse`:
// a reconciliação precisa de movimentação de verdade para ter o que conferir.
require_once __DIR__.'/../Reversal/helpers.php';

/**
 * Encerra a transação que o `RefreshDatabase` abre em volta de cada teste.
 *
 * O comando precisa de uma transação de verdade, no nível mais alto, para que
 * `SET TRANSACTION ISOLATION LEVEL` seja o primeiro statement dela. Dentro da
 * transação do teste ele viraria um savepoint, e o PostgreSQL recusaria o
 * statement — o teste provaria o contrário do que se propõe a provar.
 *
 * Desfazer aqui é seguro porque esta transação ainda está vazia: o gancho roda
 * antes de qualquer cenário ser montado. O comando continua idêntico; quem se
 * adapta é a suíte, e só nesta pasta.
 */
function encerraTransacaoDoTeste(): void
{
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}

/**
 * Limpa o que estes testes gravam de verdade.
 *
 * Sem a transação externa nada se desfaz sozinho, então a limpeza é explícita e
 * roda mesmo quando o teste falha. `RESTART IDENTITY` devolve as sequências ao
 * início, para que os ids das carteiras sejam previsíveis em cada caso.
 */
function limpaBancoDeTeste(): void
{
    DB::statement('TRUNCATE wallet_entries, transactions, wallets, users RESTART IDENTITY CASCADE');
}

/** Roda o comando de conferência de verdade e devolve o que ele respondeu. */
function conferencia(): array
{
    $codigo = Artisan::call('wallet:check');

    return ['codigo' => $codigo, 'saida' => Artisan::output()];
}

/**
 * Estraga o saldo de uma carteira sem passar por Action nenhuma.
 *
 * É a única forma honesta de testar o detector: a divergência precisa existir
 * no banco, e nenhum caminho normal da aplicação consegue criá-la.
 */
function estragaSaldoDaCarteira(int $walletId, int $saldo): void
{
    DB::table('wallets')->where('id', $walletId)->update(['balance' => $saldo]);
}

/** O mesmo estrago, agora no saldo acumulado de um lançamento. */
function estragaSaldoDoLancamento(int $entryId, int $balanceAfter): void
{
    DB::table('wallet_entries')->where('id', $entryId)->update(['balance_after' => $balanceAfter]);
}

/** Uma fotografia do que o comando não pode alterar. */
function retratoDaReconciliacao(): array
{
    return [
        'saldos' => Wallet::query()->orderBy('id')->pluck('balance', 'id')->toArray(),
        'lancamentos' => WalletEntry::query()->orderBy('id')
            ->get(['id', 'wallet_id', 'type', 'amount', 'balance_after'])->toArray(),
    ];
}
