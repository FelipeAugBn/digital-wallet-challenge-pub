<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Route::middleware('web')->get('/falha-de-teste', fn () => throw new RuntimeException('segredo interno 42'));
    Route::middleware('web')->get('/falha-de-banco', fn () => DB::select('select * from tabela_que_nao_existe'));
});

test('fora do ambiente local mostra mensagem genérica com o identificador da requisição', function () {
    $this->app->instance('env', 'production');

    $resposta = $this->get('/falha-de-teste');

    $resposta->assertStatus(500)
        ->assertSee('Algo deu errado')
        ->assertSee($resposta->headers->get(AssignRequestId::HEADER));
});

test('fora do ambiente local não expõe mensagem, classe, arquivo nem rastro da falha', function () {
    $this->app->instance('env', 'production');

    $this->get('/falha-de-teste')
        ->assertDontSee('segredo interno 42')
        ->assertDontSee('RuntimeException')
        ->assertDontSee('Stack trace')
        ->assertDontSee('vendor/')
        ->assertDontSee('/var/www');
});

test('fora do ambiente local não expõe SQL nem SQLSTATE quando a falha vem do banco', function () {
    $this->app->instance('env', 'production');

    $this->get('/falha-de-banco')
        ->assertStatus(500)
        ->assertSee('Algo deu errado')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('42P01')
        ->assertDontSee('tabela_que_nao_existe')
        ->assertDontSee('QueryException');
});

test('no ambiente local a falha continua aparecendo inteira para quem desenvolve', function () {
    $this->app->instance('env', 'local');

    // Quem desenvolve precisa da mensagem verdadeira; quem usa o sistema, nao.
    $this->get('/falha-de-teste')
        ->assertStatus(500)
        ->assertSee('segredo interno 42');
});

test('fora do ambiente local preserva os erros esperados', function () {
    $this->app->instance('env', 'production');

    // Sair do ambiente de teste liga a conferencia real do token CSRF, que o
    // formulario preenche e o `post` do teste nao; ela fica de fora daqui para
    // que o assunto deste teste continue sendo o tratamento de erro.
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $ana = userWithWallet(1_000);
    $bia = userWithWallet();
    $deBia = deposit($bia, '10,00');

    // Rota inexistente continua 404, e nao uma falha interna.
    $this->get('/rota-que-nao-existe')->assertStatus(404);

    // Visitante continua sendo mandado ao login.
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->actingAs($ana);

    // Validacao continua voltando ao formulario com a mensagem.
    $this->post(route('deposits.store'), [
        'amount' => 'dez reais',
        'idempotency_key' => (string) Str::uuid7(),
    ])->assertSessionHasErrors(['amount']);

    // Regra de negocio continua virando mensagem de tela.
    $this->post(route('transfers.store'), [
        'recipient_email' => $bia->email,
        'amount' => '999,00',
        'idempotency_key' => (string) Str::uuid7(),
    ])->assertSessionHasErrors();

    // Operacao de outra pessoa continua proibida.
    $this->post(route('reversals.store', $deBia))->assertStatus(403);
});
