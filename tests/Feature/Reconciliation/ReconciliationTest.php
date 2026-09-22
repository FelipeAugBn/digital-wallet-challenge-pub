<?php

use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

// Esta pasta roda fora da transação do `RefreshDatabase` e limpa o banco por
// conta própria; o porquê está documentado em `encerraTransacaoDoTeste()`.
beforeEach(fn () => encerraTransacaoDoTeste());
afterEach(fn () => limpaBancoDeTeste());

test('confere contra o PostgreSQL, no banco de testes', function () {
    // A reconciliação depende de transação de verdade e de tipos inteiros de
    // 64 bits. Rodar isso em SQLite provaria outra coisa.
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('wallet_testing');
});

test('fica disponível na lista de comandos do artisan', function () {
    expect(Artisan::all())->toHaveKey('wallet:check')
        ->and(Artisan::all()['wallet:check']->getDescription())->toContain('livro-razão');
});

test('banco sem carteira nenhuma é consistente', function () {
    expect(Wallet::count())->toBe(0);

    $resultado = conferencia();

    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and($resultado['saida'])->toContain('conferem');
});

test('carteira zerada e sem lançamento é consistente', function () {
    userWithWallet();

    $resultado = conferencia();

    // O livro-razão dela soma zero e o saldo guardado é zero: batem.
    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and($resultado['saida'])->toContain('1 carteira(s)')
        ->and($resultado['saida'])->toContain('0 lançamento(s)');
});

test('depósitos consistentes são consistentes', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');
    deposit($ana, '5,50');
    deposit($ana, '0,01');

    $resultado = conferencia();

    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and(walletOf($ana)->balance)->toBe(1_551)
        ->and($resultado['saida'])->toContain('3 lançamento(s)');
});

test('uma sequência real de depósitos, transferências e estornos reconcilia', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();
    $carol = userWithWallet();

    deposit($ana, '100,00');
    $depositoDaBia = deposit($bia, '40,00');
    transfer($ana, $bia, '30,00');
    $transferencia = transfer($ana, $carol, '25,00');
    deposit($carol, '7,35');
    transfer($bia, $carol, '12,00');

    reverse($transferencia, $ana);
    reverse($depositoDaBia, $bia);

    $resultado = conferencia();

    // Oito operações em três carteiras, duas delas desfeitas: se qualquer
    // `balance_after` tivesse saído da ordem, apareceria aqui.
    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and($resultado['saida'])->toContain('3 carteira(s)')
        ->and(WalletEntry::count())->toBe(12);

    // A conferência independente, feita pelo teste e não pelo comando.
    $creditos = WalletEntry::query()->where('type', 'credit')->sum('amount');
    $debitos = WalletEntry::query()->where('type', 'debit')->sum('amount');

    expect((int) $creditos - (int) $debitos)->toBe((int) Wallet::sum('balance'));
});

test('saldo negativo produzido por estorno continua reconciliando', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    $deposito = deposit($ana, '10,00');
    transfer($ana, $bia, '10,00');

    reverse($deposito, $ana);

    $resultado = conferencia();

    // A carteira fica devendo, e devendo ela ainda tem de fechar.
    expect(walletOf($ana)->balance)->toBe(-1_000)
        ->and($resultado['codigo'])->toBe(Command::SUCCESS);
});

test('detecta divergência no saldo da carteira', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    estragaSaldoDaCarteira(walletOf($ana)->id, 9_900);

    $resultado = conferencia();

    expect($resultado['codigo'])->toBe(Command::FAILURE)
        ->and($resultado['saida'])->toContain('wallets.balance')
        ->and($resultado['saida'])->toContain('esperado R$ 10,00 (1000)')
        ->and($resultado['saida'])->toContain('encontrado R$ 99,00 (9900)');
});

test('detecta saldo em carteira que não tem lançamento nenhum', function () {
    $ana = userWithWallet();

    // Dinheiro que apareceu do nada: nenhum lançamento explica esse saldo.
    // Sem este caso, uma carteira sem livro-razão passaria batido — foi a
    // única brecha que sobrou quando quebrei o comando de propósito.
    estragaSaldoDaCarteira(walletOf($ana)->id, 5_000);

    $resultado = conferencia();

    expect($resultado['codigo'])->toBe(Command::FAILURE)
        ->and($resultado['saida'])->toContain('wallets.balance')
        ->and($resultado['saida'])->toContain('esperado R$ 0,00 (0)')
        ->and($resultado['saida'])->toContain('encontrado R$ 50,00 (5000)');
});

test('detecta divergência no saldo acumulado de um lançamento', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');
    deposit($ana, '5,00');

    $primeiro = WalletEntry::query()->orderBy('id')->first();

    estragaSaldoDoLancamento($primeiro->id, 700);

    $resultado = conferencia();

    // Só o `balance_after` do primeiro lançamento está errado: o saldo da
    // carteira continua batendo com a soma, então esta é a única divergência.
    expect($resultado['codigo'])->toBe(Command::FAILURE)
        ->and($resultado['saida'])->toContain('balance_after')
        ->and($resultado['saida'])->toContain('lançamento '.$primeiro->id)
        ->and($resultado['saida'])->toContain('esperado R$ 10,00 (1000)')
        ->and($resultado['saida'])->toContain('encontrado R$ 7,00 (700)')
        ->and($resultado['saida'])->not->toContain('wallets.balance');
});

test('a saída identifica a carteira, o lançamento e o tipo da divergência', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    $carteira = walletOf($ana)->id;
    $lancamento = WalletEntry::query()->sole()->id;

    estragaSaldoDoLancamento($lancamento, 1);
    estragaSaldoDaCarteira($carteira, 2);

    $saida = conferencia()['saida'];

    expect($saida)->toContain('Carteira '.$carteira)
        ->and($saida)->toContain('lançamento '.$lancamento)
        ->and($saida)->toContain('saldo acumulado do lançamento')
        ->and($saida)->toContain('saldo da carteira');
});

test('relata todas as divergências, não só a primeira', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    deposit($ana, '10,00');
    deposit($ana, '20,00');
    deposit($bia, '30,00');

    $lancamentos = WalletEntry::query()->orderBy('id')->pluck('id')->all();

    // Três estragos em duas carteiras, de dois tipos diferentes.
    estragaSaldoDoLancamento($lancamentos[0], 111);
    estragaSaldoDoLancamento($lancamentos[2], 222);
    estragaSaldoDaCarteira(walletOf($bia)->id, 333);

    $resultado = conferencia();

    expect($resultado['codigo'])->toBe(Command::FAILURE)
        ->and($resultado['saida'])->toContain('3 divergência(s)')
        ->and($resultado['saida'])->toContain('(111)')
        ->and($resultado['saida'])->toContain('(222)')
        ->and($resultado['saida'])->toContain('(333)');
});

test('não corrige nada: os dados ficam exatamente como estavam', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();
    deposit($ana, '10,00');
    transfer($ana, $bia, '4,00');

    estragaSaldoDaCarteira(walletOf($bia)->id, 123_456);
    estragaSaldoDoLancamento(WalletEntry::query()->orderBy('id')->value('id'), 7);

    $antes = retratoDaReconciliacao();

    expect(conferencia()['codigo'])->toBe(Command::FAILURE);

    // O comando aponta o problema e vai embora. Corrigir sozinho apagaria a
    // evidência do defeito que produziu a divergência.
    expect(retratoDaReconciliacao())->toBe($antes);
});
