<?php

use App\Models\Transaction;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

it('keeps a visitor away from both the form and the submission', function () {
    $this->get(route('deposits.create'))->assertRedirect(route('login'));
    $this->post(route('deposits.store'), ['amount' => '10,00'])->assertRedirect(route('login'));

    expect(Transaction::count())->toBe(0);
});

it('serves a form with csrf and a server made key', function () {
    $response = $this->actingAs(userWithWallet())->get(route('deposits.create'));

    $response->assertOk();

    $csrf = preg_match('/name="_token" value="([^"]+)"/', $response->getContent(), $token) === 1;
    $hidden = preg_match('/name="idempotency_key" value="([^"]+)"/', $response->getContent(), $key) === 1;

    expect($csrf)->toBeTrue()
        ->and($token[1])->toBe(csrf_token())
        ->and($hidden)->toBeTrue()
        ->and($key[1])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('gives a different key to each visit of the form', function () {
    $this->actingAs(userWithWallet());

    $primeira = $this->get(route('deposits.create'))->getContent();
    $segunda = $this->get(route('deposits.create'))->getContent();

    preg_match('/name="idempotency_key" value="([^"]+)"/', $primeira, $a);
    preg_match('/name="idempotency_key" value="([^"]+)"/', $segunda, $b);

    expect($a[1])->not->toBe($b[1]);
});

it('refuses an invalid amount with a message and no transaction', function (string $amount) {
    $response = $this->actingAs(userWithWallet())
        ->from(route('deposits.create'))
        ->post(route('deposits.store'), ['amount' => $amount, 'idempotency_key' => (string) Str::uuid7()]);

    $response->assertRedirect(route('deposits.create'))->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
})->with(['', '0', '-10', '1000.50', '10,505', '1.000.000,01', 'abc']);

it('refuses a key that is not a uuid', function () {
    $this->actingAs(userWithWallet())
        ->from(route('deposits.create'))
        ->post(route('deposits.store'), ['amount' => '10,00', 'idempotency_key' => 'chave-qualquer'])
        ->assertSessionHasErrors('idempotency_key');

    expect(Transaction::count())->toBe(0);
});

it('keeps the same key when the amount is rejected', function () {
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

it('answers a successful deposit with a redirect and a message', function () {
    $response = $this->actingAs(userWithWallet())
        ->post(route('deposits.store'), ['amount' => '1.000,50', 'idempotency_key' => (string) Str::uuid7()]);

    $response->assertRedirect(route('dashboard'))
        ->assertSessionHas('sucesso', 'Depósito de R$ 1.000,50 realizado.');

    $this->get(route('dashboard'))->assertOk()->assertSee('Depósito de R$ 1.000,50 realizado.');
});

it('does not deposit again when the page after the redirect is reloaded', function () {
    $user = userWithWallet();

    $this->actingAs($user)
        ->post(route('deposits.store'), ['amount' => '10,00', 'idempotency_key' => (string) Str::uuid7()])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('dashboard'))->assertOk();

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($user)->balance)->toBe(1_000);
});

it('reaches the deposit form from the dashboard', function () {
    $this->actingAs(userWithWallet())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('deposits.create'), false);
});
