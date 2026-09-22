<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->arquivo = capturaLogs();
});

afterEach(function () {
    @unlink($this->arquivo);
});

test('escreve cada linha de log em JSON com horário, nível, mensagem e contexto', function () {
    Route::middleware('web')->get('/registro-de-prova', function () {
        Log::info('operação registrada', ['transaction_id' => 'abc-123']);
    });

    $this->get('/registro-de-prova');

    $linha = linhasDeLog($this->arquivo)[0];

    expect($linha['message'])->toBe('operação registrada')
        ->and($linha['level_name'])->toBe('INFO')
        ->and($linha['datetime'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/')
        ->and($linha['context']['transaction_id'])->toBe('abc-123');
});

test('guarda na linha de log o mesmo identificador devolvido na resposta', function () {
    Route::middleware('web')->get('/registro-de-prova', fn () => Log::info('operação registrada'));

    $resposta = $this->get('/registro-de-prova');

    expect(linhasDeLog($this->arquivo)[0]['context']['request_id'])
        ->toBe($resposta->headers->get(AssignRequestId::HEADER));
});

test('não registra senha, cookie, token CSRF ou segredo', function () {
    Route::middleware('web')->get('/registro-de-prova', function () {
        Log::warning('tentativa suspeita', [
            'password' => 'senha-do-formulario',
            'password_confirmation' => 'senha-do-formulario',
            '_token' => 'token-csrf-da-sessao',
            'cookie' => 'wallet_session=valor-do-cookie',
            'authorization' => 'Bearer credencial-da-integracao',
            'dados' => ['api_secret' => 'chave-da-integracao'],
            'email' => 'ana@example.test',
        ]);
    });

    $this->get('/registro-de-prova');

    $vazamentos = array_values(array_filter(
        ['senha-do-formulario', 'token-csrf-da-sessao', 'valor-do-cookie', 'credencial-da-integracao', 'chave-da-integracao'],
        fn (string $segredo) => str_contains((string) file_get_contents($this->arquivo), $segredo),
    ));

    $contexto = linhasDeLog($this->arquivo)[0]['context'];

    expect($vazamentos)->toBe([])
        ->and($contexto['password'])->toBe('[redigido]')
        ->and($contexto['dados']['api_secret'])->toBe('[redigido]')
        // O que nao e segredo continua no log: sem isso nao ha investigacao.
        ->and($contexto['email'])->toBe('ana@example.test');
});

test('não deixa a senha do formulário chegar ao log quando a requisição falha', function () {
    Route::middleware('web')->post('/falha-com-senha', fn () => throw new RuntimeException('falha depois do formulário'));

    $this->post('/falha-com-senha', [
        'email' => 'ana@example.test',
        'password' => 'senha-do-formulario',
    ]);

    $conteudo = (string) file_get_contents($this->arquivo);
    $linha = linhasDeLog($this->arquivo)[0];

    expect(str_contains($conteudo, 'senha-do-formulario'))->toBeFalse()
        ->and($linha['level_name'])->toBe('ERROR')
        ->and($linha['context']['request_id'])->toMatch('/^[0-9a-f-]{36}$/');
});

test('manda os logs da aplicação para o stderr em JSON', function () {
    expect(config('logging.channels.stderr.handler_with.stream'))->toBe('php://stderr')
        ->and(config('logging.channels.stderr.formatter'))->toBe(JsonFormatter::class)
        ->and(file_get_contents(base_path('.env.example')))->toContain('LOG_CHANNEL=stderr');
});
