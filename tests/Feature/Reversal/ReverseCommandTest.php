<?php

use App\Actions\ReverseTransaction;
use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

require_once __DIR__.'/helpers.php';

test('fica disponível na lista de comandos do artisan', function () {
    expect(Artisan::all())->toHaveKey('wallet:reverse');

    // Aparecer na lista não basta: sem descrição, quem abre o `artisan list`
    // não descobre para que o comando serve nem quando usá-lo.
    expect(Artisan::all()['wallet:reverse']->getDescription())
        ->toContain('inconsistência');
});

test('estorna um depósito por inconsistência', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $resultado = estornoOperacional(parametrosDoComando($deposito->id));

    $estorno = Transaction::query()->where('original_transaction_id', $deposito->id)->sole();

    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and($estorno->type)->toBe(TransactionType::Reversal)
        ->and($estorno->amount)->toBe(1_000)
        ->and($deposito->fresh()->status)->toBe(TransactionStatus::Reversed)
        ->and($deposito->fresh()->amount)->toBe(1_000)
        ->and(walletOf($ana)->balance)->toBe(0);

    // O lançamento inverso é do estorno, não uma edição do lançamento original.
    $lancamento = WalletEntry::query()->where('transaction_id', $estorno->id)->sole();

    expect($lancamento->type)->toBe(WalletEntryType::Debit)
        ->and($lancamento->amount)->toBe(1_000)
        ->and($lancamento->balance_after)->toBe(0);
});

test('estorna uma transferência por inconsistência, devolvendo à origem e retirando do destino', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    $transferencia = transfer($ana, $bia, '30,00');

    $resultado = estornoOperacional(parametrosDoComando($transferencia->id));

    $estorno = Transaction::query()->where('original_transaction_id', $transferencia->id)->sole();

    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and(walletOf($ana)->balance)->toBe(5_000)
        ->and(walletOf($bia)->balance)->toBe(0)
        ->and($transferencia->fresh()->status)->toBe(TransactionStatus::Reversed);

    $lancamentos = WalletEntry::query()->where('transaction_id', $estorno->id)
        ->get()->keyBy(fn (WalletEntry $entry) => $entry->wallet_id);

    // Os dois lados no mesmo estorno: crédito para quem enviou, débito para
    // quem recebeu, cada um com o saldo que ficou naquele ponto.
    expect($lancamentos[walletOf($ana)->id]->type)->toBe(WalletEntryType::Credit)
        ->and($lancamentos[walletOf($ana)->id]->balance_after)->toBe(5_000)
        ->and($lancamentos[walletOf($bia)->id]->type)->toBe(WalletEntryType::Debit)
        ->and($lancamentos[walletOf($bia)->id]->balance_after)->toBe(0);
});

test('registra o motivo como inconsistência e deixa o estorno sem autor', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    estornoOperacional(parametrosDoComando($deposito->id));

    $estorno = Transaction::query()->where('original_transaction_id', $deposito->id)->sole();

    // Sem autor porque não houve pessoa pedindo: quem disparou foi a operação.
    expect($estorno->reversal_reason)->toBe(ReversalReason::Inconsistency)
        ->and($estorno->initiated_by_user_id)->toBeNull()
        ->and($estorno->idempotency_key)->toBeNull();
});

test('permite que o estorno deixe a carteira negativa', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    $deposito = deposit($ana, '10,00');
    transfer($ana, $bia, '10,00');

    // O dinheiro depositado já saiu: desfazer o depósito tem de acontecer
    // mesmo assim, e o buraco fica registrado como saldo negativo.
    expect(walletOf($ana)->balance)->toBe(0);

    $resultado = estornoOperacional(parametrosDoComando($deposito->id));

    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and(walletOf($ana)->balance)->toBe(-1_000);
});

test('devolve código zero e nomeia a operação original e o estorno', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $resultado = estornoOperacional(parametrosDoComando($deposito->id));

    $estorno = Transaction::query()->where('original_transaction_id', $deposito->id)->sole();

    expect($resultado['codigo'])->toBe(0)
        ->and($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and($resultado['saida'])->toContain($deposito->id)
        ->and($resultado['saida'])->toContain($estorno->id)
        ->and($resultado['saida'])->toContain('inconsistência');
});

test('recusa a ausência do motivo sem tocar em dado nenhum', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $antes = retratoFinanceiro();

    $resultado = estornoOperacional(parametrosDoComando($deposito->id, reason: null));

    expect($resultado['codigo'])->toBe(Command::INVALID)
        ->and($resultado['saida'])->toContain('--reason=inconsistency')
        ->and(retratoFinanceiro())->toBe($antes);
});

test('recusa o motivo {motivo} sem tocar em dado nenhum', function (string $motivo) {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $antes = retratoFinanceiro();

    $resultado = estornoOperacional(parametrosDoComando($deposito->id, $motivo));

    expect($resultado['codigo'])->toBe(Command::INVALID)
        ->and(retratoFinanceiro())->toBe($antes);
})->with([
    // O pedido da pessoa tem dono e autorização; ele não entra por aqui.
    'motivo da tela' => 'user_request',
    // Caixa diferente é valor diferente: a comparação é estrita de propósito.
    'tudo em maiúsculas' => 'INCONSISTENCY',
    'primeira maiúscula' => 'Inconsistency',
    'em português' => 'inconsistencia',
    'com espaço em volta' => ' inconsistency ',
    'inventado' => 'fraude',
    'vazio' => '',
]);

test('recusa um identificador fora do formato UUID sem consultar o banco', function () {
    $resultado = null;

    $consultas = consultasDe(function () use (&$resultado) {
        $resultado = estornoOperacional(parametrosDoComando('nao-e-um-uuid'));
    });

    // Sem a conferência de formato, esse texto chegaria ao PostgreSQL e
    // voltaria como erro de conversão de tipo em vez de instrução.
    expect($resultado['codigo'])->toBe(Command::INVALID)
        ->and($resultado['saida'])->toContain('formato UUID')
        ->and($consultas)->toBeEmpty();
});

test('avisa quando o identificador é válido mas não existe operação', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    $antes = retratoFinanceiro();

    $resultado = estornoOperacional(parametrosDoComando('0192f3a4-5b6c-7d8e-9f01-23456789abcd'));

    expect($resultado['codigo'])->toBe(Command::FAILURE)
        ->and($resultado['saida'])->toContain('Não encontramos essa operação.')
        ->and(retratoFinanceiro())->toBe($antes);
});

test('recusa o segundo estorno e não cria uma segunda reversão', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    estornoOperacional(parametrosDoComando($deposito->id));

    $antes = retratoFinanceiro();

    $resultado = estornoOperacional(parametrosDoComando($deposito->id));

    expect($resultado['codigo'])->toBe(Command::FAILURE)
        ->and($resultado['saida'])->toContain('Esta operação já foi estornada.')
        ->and(Transaction::query()->where('original_transaction_id', $deposito->id)->count())->toBe(1)
        ->and(retratoFinanceiro())->toBe($antes);
});

test('recusa estornar um estorno', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    estornoOperacional(parametrosDoComando($deposito->id));

    $estorno = Transaction::query()->where('original_transaction_id', $deposito->id)->sole();

    $antes = retratoFinanceiro();

    $resultado = estornoOperacional(parametrosDoComando($estorno->id));

    // Desfazer um estorno recriaria o dinheiro que ele tirou.
    expect($resultado['codigo'])->toBe(Command::FAILURE)
        ->and($resultado['saida'])->toContain('Esta operação não pode ser estornada.')
        ->and(Transaction::count())->toBe(2)
        ->and(retratoFinanceiro())->toBe($antes);
});

test('nenhuma recusa expõe SQLSTATE, nome de classe ou rastro de pilha', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');
    estornoOperacional(parametrosDoComando($deposito->id));
    $estorno = Transaction::query()->where('original_transaction_id', $deposito->id)->sole();

    $saidas = [
        'sem motivo' => estornoOperacional(parametrosDoComando($deposito->id, reason: null))['saida'],
        'motivo da tela' => estornoOperacional(parametrosDoComando($deposito->id, 'user_request'))['saida'],
        'uuid torto' => estornoOperacional(parametrosDoComando('xxx'))['saida'],
        'inexistente' => estornoOperacional(parametrosDoComando('0192f3a4-5b6c-7d8e-9f01-23456789abcd'))['saida'],
        'já estornada' => estornoOperacional(parametrosDoComando($deposito->id))['saida'],
        'estorno de estorno' => estornoOperacional(parametrosDoComando($estorno->id))['saida'],
    ];

    // O operador tem acesso ao shell, mas a saída do comando continua sendo
    // texto para gente: nada de tabela, constraint, classe ou arquivo.
    $proibidos = ['SQLSTATE', '23505', '22P02', 'App\\', 'Exception', 'vendor/', '.php', 'for update', 'transactions', 'Stack trace'];

    // Os vazamentos são acumulados em vez de conferidos um a um: a falha nomeia
    // qual caminho vazou o quê, e `not->toContain` com dois argumentos passaria
    // sempre que um deles faltasse — que é o jeito silencioso de errar isto.
    $vazamentos = [];
    $mudos = [];

    foreach ($saidas as $caminho => $saida) {
        if (trim($saida) === '') {
            $mudos[] = $caminho;
        }

        foreach ($proibidos as $proibido) {
            if (str_contains($saida, $proibido)) {
                $vazamentos[] = $caminho.' vazou '.$proibido;
            }
        }
    }

    expect($vazamentos)->toBe([])
        ->and($mudos)->toBe([]);
});

test('delega para a Action, com o motivo de inconsistência e sem quem pediu', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    // A Action verdadeira sai de cena: o que sobra é o comando sozinho.
    $this->mock(ReverseTransaction::class)
        ->shouldReceive('handle')
        ->once()
        ->with($deposito->id, ReversalReason::Inconsistency, null)
        ->andReturn($deposito);

    $antes = retratoFinanceiro();

    $resultado = estornoOperacional(parametrosDoComando($deposito->id));

    // Sem a Action nada acontece com o dinheiro. Se o comando tivesse uma
    // cópia da regra, este retrato mudaria e o teste quebraria aqui.
    expect($resultado['codigo'])->toBe(Command::SUCCESS)
        ->and(retratoFinanceiro())->toBe($antes);
});

test('não lê a operação antes de entregá-la à Action', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $consultas = consultasDe(fn () => estornoOperacional(parametrosDoComando($deposito->id)));

    $leituras = array_values(array_filter(
        $consultas,
        fn (array $consulta) => str_contains($consulta['sql'], 'select')
            && str_contains($consulta['sql'], 'from "transactions"'),
    ));

    // Uma leitura só, e já bloqueada. Uma consulta prévia no comando decidiria
    // com um status lido fora do lock — exatamente o que a T010 evitou.
    expect($leituras)->toHaveCount(1)
        ->and($leituras[0]['sql'])->toContain('for update');
});
