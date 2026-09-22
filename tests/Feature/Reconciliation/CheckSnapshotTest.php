<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

// Mesmo arranjo da outra suíte desta pasta: sem a transação externa do
// `RefreshDatabase`, porque é justamente a transação do comando que está sendo
// examinada aqui. Ver `encerraTransacaoDoTeste()`.
beforeEach(fn () => encerraTransacaoDoTeste());
afterEach(fn () => limpaBancoDeTeste());

/** Monta um cenário com movimento nas três carteiras e ouve o comando rodar. */
function consultasDaConferencia(): array
{
    $ana = userWithWallet();
    $bia = userWithWallet();
    deposit($ana, '10,00');
    transfer($ana, $bia, '4,00');
    reverse(deposit($bia, '1,00'), $bia);

    return consultasDe(fn () => conferencia());
}

test('abre uma transação de verdade, e não um savepoint dentro de outra', function () {
    // Se sobrasse uma transação aberta em volta, `DB::transaction()` viraria um
    // savepoint e `SET TRANSACTION ISOLATION LEVEL` não teria efeito nenhum.
    expect(DB::transactionLevel())->toBe(0);

    expect(conferencia()['codigo'])->toBe(Command::SUCCESS)
        ->and(DB::transactionLevel())->toBe(0);
});

test('executa SET TRANSACTION ISOLATION LEVEL REPEATABLE READ como primeiro statement', function () {
    $consultas = consultasDaConferencia();

    // Primeiro de verdade: posição zero. O PostgreSQL só aceita definir o nível
    // de isolamento antes da primeira consulta da transação — depois dela, o
    // statement é recusado e a conferência inteira falharia.
    expect($consultas[0]['sql'])->toBe('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
});

test('nenhuma consulta acontece antes do statement de isolamento', function () {
    $consultas = consultasDaConferencia();

    $isolamento = posicoesCom($consultas, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

    $leiturasAntes = array_filter(
        array_slice($consultas, 0, $isolamento[0]),
        fn (array $consulta) => str_contains(strtolower($consulta['sql']), 'select'),
    );

    expect($isolamento)->toBe([0])
        ->and($leiturasAntes)->toBeEmpty();
});

test('reforça o modo somente leitura no PostgreSQL logo depois do isolamento', function () {
    $consultas = consultasDaConferencia();

    // Defesa em profundidade: com a transação marcada como somente leitura,
    // qualquer escrita que aparecesse aqui viraria erro do banco.
    expect($consultas[1]['sql'])->toBe('SET TRANSACTION READ ONLY');
});

test('lê carteiras e lançamentos depois de fixado o snapshot', function () {
    $consultas = consultasDaConferencia();

    $carteiras = posicoesCom($consultas, 'select', 'from "wallets"');
    $lancamentos = posicoesCom($consultas, 'select', 'from "wallet_entries"');

    // As duas fontes comparadas precisam vir do mesmo instante; se uma delas
    // fosse lida antes do snapshot, o comando poderia inventar divergência.
    expect($carteiras)->not->toBeEmpty()
        ->and($lancamentos)->not->toBeEmpty()
        ->and(min($carteiras))->toBeGreaterThan(1)
        ->and(min($lancamentos))->toBeGreaterThan(1);
});

test('não emite INSERT, UPDATE, DELETE nem FOR UPDATE', function () {
    $consultas = consultasDaConferencia();

    $escritas = [];
    $locks = [];

    foreach ($consultas as $consulta) {
        $sql = strtolower(trim($consulta['sql']));

        if (preg_match('/^(insert|update|delete|truncate|alter|drop)\b/', $sql) === 1) {
            $escritas[] = $consulta['sql'];
        }

        if (str_contains($sql, 'for update') || str_contains($sql, 'for share')) {
            $locks[] = $consulta['sql'];
        }
    }

    // Conferir o saldo de alguém não pode competir com quem está depositando.
    expect($escritas)->toBe([])
        ->and($locks)->toBe([]);
});

test('lê o livro-razão em ordem determinística, por carteira e por id', function () {
    $consultas = consultasDaConferencia();

    $lancamentos = posicoesCom($consultas, 'from "wallet_entries"');

    // `id` crescente é a ordem em que os saldos foram confirmados, porque o
    // lock da carteira dura até o commit. É ela que torna o `balance_after`
    // verificável; sem o desempate, a soma parcial não significaria nada.
    expect($consultas[$lancamentos[0]]['sql'])
        ->toContain('order by "wallet_id" asc, "id" asc');
});
