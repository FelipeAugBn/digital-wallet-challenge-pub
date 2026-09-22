<?php

use App\Models\Transaction;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('mantém o visitante fora do formulário e do envio', function () {
    $this->get(route('deposits.create'))->assertRedirect(route('login'));
    $this->post(route('deposits.store'), ['amount' => '10,00'])->assertRedirect(route('login'));

    expect(Transaction::count())->toBe(0);
});

test('entrega o formulário com CSRF e chave gerada no servidor', function () {
    $response = $this->actingAs(userWithWallet())->get(route('deposits.create'));

    $response->assertOk();

    $csrf = preg_match('/name="_token" value="([^"]+)"/', $response->getContent(), $token) === 1;
    $hidden = preg_match('/name="idempotency_key" value="([^"]+)"/', $response->getContent(), $key) === 1;

    expect($csrf)->toBeTrue()
        ->and($token[1])->toBe(csrf_token())
        ->and($hidden)->toBeTrue()
        ->and($key[1])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

test('dá uma chave diferente a cada visita ao formulário', function () {
    $this->actingAs(userWithWallet());

    $primeira = $this->get(route('deposits.create'))->getContent();
    $segunda = $this->get(route('deposits.create'))->getContent();

    preg_match('/name="idempotency_key" value="([^"]+)"/', $primeira, $a);
    preg_match('/name="idempotency_key" value="([^"]+)"/', $segunda, $b);

    expect($a[1])->not->toBe($b[1]);
});

test('recusa valor inválido com mensagem e sem criar transação', function (string $amount) {
    $response = $this->actingAs(userWithWallet())
        ->from(route('deposits.create'))
        ->post(route('deposits.store'), ['amount' => $amount, 'idempotency_key' => (string) Str::uuid7()]);

    $response->assertRedirect(route('deposits.create'))->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
})->with(['', '0', '-10', '1000.50', '10,505', '1.000.000,01', 'abc']);

test('recusa chave que não é UUID', function () {
    $this->actingAs(userWithWallet())
        ->from(route('deposits.create'))
        ->post(route('deposits.store'), ['amount' => '10,00', 'idempotency_key' => 'chave-qualquer'])
        ->assertSessionHasErrors('idempotency_key');

    expect(Transaction::count())->toBe(0);
});

test('mantém a mesma chave quando o valor é recusado', function () {
    $key = (string) Str::uuid7();

    $this->actingAs(userWithWallet())
        ->from(route('deposits.create'))
        ->post(route('deposits.store'), ['amount' => 'abc', 'idempotency_key' => $key])
        ->assertSessionHasErrors('amount');

    // O formulario reaberto recebe a chave antiga de volta, entao a correcao
    // continua sendo a mesma operacao e nao uma segunda.
    expect(session()->getOldInput('idempotency_key'))->toBe($key)
        ->and($this->get(route('deposits.create'))->getContent())->toContain('value="'.$key.'"');
});

test('responde ao depósito bem-sucedido com redirect e mensagem', function () {
    $response = $this->actingAs(userWithWallet())
        ->post(route('deposits.store'), ['amount' => '1.000,50', 'idempotency_key' => (string) Str::uuid7()]);

    $response->assertRedirect(route('dashboard'))
        ->assertSessionHas('sucesso', 'Depósito de R$ 1.000,50 realizado.');

    $this->get(route('dashboard'))->assertOk()->assertSee('Depósito de R$ 1.000,50 realizado.');
});

test('não deposita de novo quando a página do redirect é recarregada', function () {
    $user = userWithWallet();

    $this->actingAs($user)
        ->post(route('deposits.store'), ['amount' => '10,00', 'idempotency_key' => (string) Str::uuid7()])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('dashboard'))->assertOk();

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($user)->balance)->toBe(1_000);
});

test('chega ao formulário de depósito pelo painel', function () {
    $this->actingAs(userWithWallet())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('deposits.create'), false);
});
