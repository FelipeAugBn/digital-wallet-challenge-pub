<?php

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/helpers.php';

/** O identificador que a resposta devolveu no header. */
function identificadorDe(TestResponse $resposta): ?string
{
    return $resposta->headers->get(AssignRequestId::HEADER);
}

test('devolve um X-Request-ID em UUIDv7 em toda resposta', function () {
    $resposta = $this->get('/');

    expect(identificadorDe($resposta))->toMatch(UUID_V7);
});

test('gera um identificador diferente a cada requisição', function () {
    $primeiro = identificadorDe($this->get('/'));
    $segundo = identificadorDe($this->get('/'));

    expect($primeiro)->not->toBe($segundo)
        ->and($segundo)->toMatch(UUID_V7);
});

test('ignora o X-Request-ID enviado pelo cliente', function () {
    $resposta = $this->withHeader(AssignRequestId::HEADER, 'forjado-pelo-cliente')->get('/');

    expect(identificadorDe($resposta))
        ->not->toBe('forjado-pelo-cliente')
        ->toMatch(UUID_V7);
});

test('não deixa o valor do cliente chegar a quem lê o header dentro da aplicação', function () {
    Route::middleware('web')->get('/eco-do-identificador', fn (Request $request) => $request->header(AssignRequestId::HEADER));

    $resposta = $this->withHeader(AssignRequestId::HEADER, 'forjado-pelo-cliente')->get('/eco-do-identificador');

    expect($resposta->getContent())
        ->not->toBe('forjado-pelo-cliente')
        ->toBe(identificadorDe($resposta));
});

test('devolve o identificador também nas respostas de erro', function () {
    Route::middleware('web')->get('/falha-de-teste', fn () => throw new RuntimeException('segredo interno 42'));

    $this->app->instance('env', 'production');

    $rotaInexistente = $this->get('/rota-que-nao-existe');
    $semAutenticacao = $this->get(route('dashboard'));
    $falhaInesperada = $this->get('/falha-de-teste');

    expect($rotaInexistente->getStatusCode())->toBe(404)
        ->and(identificadorDe($rotaInexistente))->toMatch(UUID_V7)
        ->and($semAutenticacao->getStatusCode())->toBe(302)
        ->and(identificadorDe($semAutenticacao))->toMatch(UUID_V7)
        ->and($falhaInesperada->getStatusCode())->toBe(500)
        ->and(identificadorDe($falhaInesperada))->toMatch(UUID_V7);
});

test('devolve o identificador quando o limite de requisições barra o envio', function () {
    User::factory()->create(['email' => 'ana@example.test']);

    foreach (range(1, 5) as $tentativa) {
        tentativaDeLogin('ana@example.test');
    }

    $barrada = tentativaDeLogin('ana@example.test');

    $barrada->assertSessionHasErrors(['limite']);
    expect(identificadorDe($barrada))->toMatch(UUID_V7);
});
