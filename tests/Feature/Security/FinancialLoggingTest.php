<?php

use App\Actions\ReverseTransaction;
use App\Enums\ReversalReason;
use App\Exceptions\ReversalNotAllowed;
use App\Http\Middleware\AssignRequestId;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/../Reversal/helpers.php';

beforeEach(function () {
    $this->arquivo = capturaLogs();
    $this->ana = usuarioComCotaLimpa();
    $this->actingAs($this->ana);
});

afterEach(function () {
    @unlink($this->arquivo);
});

test('registra o depósito concluído com os identificadores e o request ID da resposta', function () {
    $resposta = postDeposito(['amount' => '25,00'])->assertRedirect(route('dashboard'));

    $deposito = Transaction::query()->sole();
    $linha = logsFinanceiros($this->arquivo)[0];

    expect($linha['level_name'])->toBe('INFO')
        ->and($linha['context']['transaction_id'])->toBe($deposito->id)
        ->and($linha['context']['type'])->toBe('deposit')
        ->and($linha['context']['result'])->toBe('completed')
        ->and($linha['context']['initiated_by_user_id'])->toBe($this->ana->id)
        ->and($linha['context']['source_wallet_id'])->toBeNull()
        ->and($linha['context']['destination_wallet_id'])->toBe(walletOf($this->ana)->id)
        ->and($linha['context']['request_id'])->toBe($resposta->headers->get(AssignRequestId::HEADER));
});

test('registra a transferência concluída com as duas carteiras envolvidas', function () {
    $bia = usuarioComCotaLimpa();
    deposit($this->ana, '30,00');

    $resposta = postTransferencia($bia->email, ['amount' => '10,00'])
        ->assertRedirect(route('dashboard'));

    $contexto = contextosFinanceiros($this->arquivo, 'transfer')[0];

    expect($contexto['transaction_id'])->toBe(Transaction::query()->where('type', 'transfer')->sole()->id)
        ->and($contexto['result'])->toBe('completed')
        ->and($contexto['initiated_by_user_id'])->toBe($this->ana->id)
        ->and($contexto['source_wallet_id'])->toBe(walletOf($this->ana)->id)
        ->and($contexto['destination_wallet_id'])->toBe(walletOf($bia)->id)
        ->and($contexto['request_id'])->toBe($resposta->headers->get(AssignRequestId::HEADER));
});

test('registra o estorno concluído apontando a operação original', function () {
    $deposito = deposit($this->ana, '40,00');

    $resposta = $this->post(route('reversals.store', $deposito))->assertRedirect();

    $contexto = contextosFinanceiros($this->arquivo, 'reversal')[0];
    $estorno = Transaction::query()->where('type', 'reversal')->sole();

    expect($contexto['transaction_id'])->toBe($estorno->id)
        ->and($contexto['result'])->toBe('completed')
        ->and($contexto['original_transaction_id'])->toBe($deposito->id)
        ->and($contexto['initiated_by_user_id'])->toBe($this->ana->id)
        // O dinheiro volta pelo caminho inverso: sai da carteira que recebeu.
        ->and($contexto['source_wallet_id'])->toBe(walletOf($this->ana)->id)
        ->and($contexto['destination_wallet_id'])->toBeNull()
        ->and($contexto['request_id'])->toBe($resposta->headers->get(AssignRequestId::HEADER));
});

test('segue cada operação pela requisição que a pediu, uma linha JSON por operação', function () {
    $bia = usuarioComCotaLimpa();

    $doDeposito = postDeposito(['amount' => '50,00'])->headers->get(AssignRequestId::HEADER);
    $daTransferencia = postTransferencia($bia->email, ['amount' => '10,00'])->headers->get(AssignRequestId::HEADER);

    $deposito = Transaction::query()->where('type', 'deposit')->sole();
    $doEstorno = $this->post(route('reversals.store', $deposito))->headers->get(AssignRequestId::HEADER);

    $contextos = contextosFinanceiros($this->arquivo);

    expect(array_column($contextos, 'type'))->toBe(['deposit', 'transfer', 'reversal'])
        ->and(array_column($contextos, 'result'))->toBe(['completed', 'completed', 'completed'])
        ->and(array_column($contextos, 'request_id'))->toBe([$doDeposito, $daTransferencia, $doEstorno])
        // Três requisições diferentes, três identificadores diferentes.
        ->and(array_unique(array_column($contextos, 'request_id')))->toHaveCount(3);
});

test('não registra e-mail, chave de idempotência nem token nas linhas financeiras', function () {
    $bia = usuarioComCotaLimpa();
    $chave = (string) Str::uuid7();

    postDeposito(['amount' => '80,00', 'idempotency_key' => $chave]);
    postTransferencia($bia->email, ['amount' => '10,00', 'idempotency_key' => (string) Str::uuid7()]);

    $conteudo = (string) file_get_contents($this->arquivo);

    $vazamentos = array_values(array_filter(
        [$this->ana->email, $bia->email, $chave, session()->token(), 'password', 'senha'],
        fn (string $sensivel) => str_contains($conteudo, $sensivel),
    ));

    expect($vazamentos)->toBe([])
        // E as linhas existem mesmo: a ausência acima não é um arquivo vazio.
        ->and(logsFinanceiros($this->arquivo))->toHaveCount(2);
});

test('marca a repetição pela chave de idempotência como repetida, e não como operação nova', function () {
    $chave = (string) Str::uuid7();

    postDeposito(['amount' => '10,00', 'idempotency_key' => $chave]);
    postDeposito(['amount' => '10,00', 'idempotency_key' => $chave]);

    $contextos = contextosFinanceiros($this->arquivo);

    expect(array_column($contextos, 'result'))->toBe(['completed', 'repeated'])
        ->and($contextos[0]['transaction_id'])->toBe($contextos[1]['transaction_id'])
        ->and(Transaction::count())->toBe(1);
});

test('registra a recusa do depósito por conflito de idempotência, sem log falso de sucesso', function () {
    $chave = (string) Str::uuid7();

    postDeposito(['amount' => '10,00', 'idempotency_key' => $chave]);
    postDeposito(['amount' => '99,00', 'idempotency_key' => $chave])->assertSessionHasErrors();

    $linhas = logsFinanceiros($this->arquivo);
    $recusa = $linhas[1];

    expect($linhas)->toHaveCount(2)
        ->and($recusa['level_name'])->toBe('WARNING')
        ->and($recusa['context']['type'])->toBe('deposit')
        ->and($recusa['context']['result'])->toBe('refused')
        ->and($recusa['context']['reason'])->toBe('idempotency_conflict')
        ->and($recusa['context']['initiated_by_user_id'])->toBe($this->ana->id)
        ->and($recusa['context'])->not->toHaveKey('transaction_id')
        // Só o primeiro depósito é sucesso; o segundo não gravou nada.
        ->and(array_column(array_column($linhas, 'context'), 'result'))->toBe(['completed', 'refused'])
        ->and(Transaction::count())->toBe(1);
});

test('registra o conflito de idempotência uma única vez, como aviso e não como erro', function () {
    $chave = (string) Str::uuid7();

    postDeposito(['amount' => '10,00', 'idempotency_key' => $chave]);

    $recusada = postDeposito(['amount' => '99,00', 'idempotency_key' => $chave]);
    $requestId = $recusada->headers->get(AssignRequestId::HEADER);

    // Todas as linhas daquela requisição, e não só as financeiras: o que
    // sobrava aqui era justamente uma linha que `logsFinanceiros` não vê.
    $daRecusa = array_values(array_filter(
        linhasDeLog($this->arquivo),
        fn (array $linha) => ($linha['context']['request_id'] ?? null) === $requestId,
    ));

    $erros = array_values(array_filter($daRecusa, fn (array $linha) => $linha['level_name'] === 'ERROR'));

    $recusada->assertSessionHasErrors([
        'amount' => 'Esta operação já foi registrada com outros dados. Confira os dados e envie novamente.',
    ]);

    expect($daRecusa)->toHaveCount(1)
        ->and($daRecusa[0]['level_name'])->toBe('WARNING')
        ->and($daRecusa[0]['message'])->toBe('operação financeira recusada')
        ->and($daRecusa[0]['context']['reason'])->toBe('idempotency_conflict')
        // Recusa prevista não é falha: nenhuma linha de erro para esta requisição.
        ->and($erros)->toBe([])
        ->and(Transaction::count())->toBe(1);
});

test('registra as recusas da transferência sem log de sucesso e sem o e-mail', function (string $destinatario, string $valor, string $motivo) {
    $bia = usuarioComCotaLimpa();
    deposit($this->ana, '5,00');

    postTransferencia($destinatario === 'bia' ? $bia->email : $destinatario, ['amount' => $valor])
        ->assertSessionHasErrors();

    $recusas = contextosFinanceiros($this->arquivo, 'transfer');

    expect($recusas)->toHaveCount(1)
        ->and($recusas[0]['result'])->toBe('refused')
        ->and($recusas[0]['reason'])->toBe($motivo)
        ->and($recusas[0]['initiated_by_user_id'])->toBe($this->ana->id)
        ->and(str_contains((string) file_get_contents($this->arquivo), $bia->email))->toBeFalse()
        ->and(Transaction::query()->where('type', 'transfer')->count())->toBe(0);
})->with([
    'destinatário inexistente' => ['ninguem@example.test', '1,00', 'recipient_not_found'],
    'saldo insuficiente' => ['bia', '500,00', 'insufficient_funds'],
]);

test('registra a transferência recusada para o próprio e-mail', function () {
    deposit($this->ana, '20,00');

    postTransferencia($this->ana->email, ['amount' => '1,00'])->assertSessionHasErrors();

    $recusas = contextosFinanceiros($this->arquivo, 'transfer');

    expect($recusas)->toHaveCount(1)
        ->and($recusas[0]['reason'])->toBe('transfer_to_self')
        ->and($recusas[0]['result'])->toBe('refused');
});

test('registra a recusa do segundo estorno da mesma operação', function () {
    $deposito = deposit($this->ana, '40,00');

    $this->post(route('reversals.store', $deposito));
    $this->post(route('reversals.store', $deposito));

    $estornos = contextosFinanceiros($this->arquivo, 'reversal');

    expect($estornos)->toHaveCount(2)
        ->and($estornos[0]['result'])->toBe('completed')
        ->and($estornos[1]['result'])->toBe('refused')
        ->and($estornos[1]['reason'])->toBe('already_reversed')
        ->and($estornos[1]['original_transaction_id'])->toBe($deposito->id)
        ->and(Transaction::query()->where('type', 'reversal')->count())->toBe(1);
});

test('registra a recusa do estorno pedido por quem não iniciou a operação', function () {
    $bia = usuarioComCotaLimpa();
    $deBia = deposit($bia, '10,00');

    // Pela tela a Policy recusa antes da Action; chamando a Action direto, a
    // segunda barreira responde — e é essa recusa que precisa ficar no log.
    expect(fn () => app(ReverseTransaction::class)->handle(
        $deBia->id,
        ReversalReason::UserRequest,
        $this->ana,
    ))->toThrow(ReversalNotAllowed::class);

    $recusas = contextosFinanceiros($this->arquivo, 'reversal');

    expect($recusas)->toHaveCount(1)
        ->and($recusas[0]['reason'])->toBe('reversal_not_allowed')
        ->and($recusas[0]['initiated_by_user_id'])->toBe($this->ana->id)
        ->and(Transaction::query()->where('type', 'reversal')->count())->toBe(0);
});

test('registra a recusa vinda do comando operacional, sem inventar identificador de requisição', function () {
    $resultado = estornoOperacional(parametrosDoComando((string) Str::uuid7()));

    $recusas = contextosFinanceiros($this->arquivo, 'reversal');

    expect($resultado['codigo'])->toBe(1)
        ->and($recusas)->toHaveCount(1)
        ->and($recusas[0]['reason'])->toBe('transaction_not_found')
        ->and($recusas[0]['initiated_by_user_id'])->toBeNull()
        // Fora de uma requisição não existe request ID, e nenhum é inventado.
        ->and($recusas[0])->not->toHaveKey('request_id');
});

test('não registra sucesso quando a transação maior volta atrás', function () {
    try {
        DB::transaction(function () {
            deposit($this->ana, '10,00');

            throw new RuntimeException('a operação maior desistiu');
        });
    } catch (RuntimeException) {
        // A falha é o assunto do teste: o que importa é o que sobrou no log.
    }

    expect(logsFinanceiros($this->arquivo))->toBe([])
        ->and(Transaction::count())->toBe(0)
        ->and(walletOf($this->ana)->balance)->toBe(0);
});
