<?php

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = userWithWallet();
    $this->actingAs($this->user);
});

test('aceita vinte POSTs financeiros por minuto e recusa o vigésimo primeiro', function () {
    foreach (range(1, 20) as $envio) {
        postDeposito()->assertRedirect(route('dashboard'));
    }

    postDeposito()->assertSessionHasErrors(['limite']);

    // O vigesimo primeiro nao chegou ao controller: nenhum deposito a mais.
    expect(Transaction::count())->toBe(20)
        ->and(walletOf($this->user)->balance)->toBe(2_000);
});

test('divide a mesma cota entre depósito, transferência e estorno', function () {
    $bia = userWithWallet();
    $original = deposit($this->user, '50,00');

    foreach (range(1, 18) as $envio) {
        postDeposito();
    }

    $this->post(route('transfers.store'), [
        'recipient_email' => $bia->email,
        'amount' => '1,00',
        'idempotency_key' => (string) Str::uuid7(),
    ])->assertRedirect(route('dashboard'));

    $this->post(route('reversals.store', $original))->assertRedirect();

    // Vinte usados: dezoito depositos, uma transferencia e um estorno.
    postDeposito()->assertSessionHasErrors(['limite']);

    expect($original->refresh()->status)->toBe(TransactionStatus::Reversed);
});

test('dá cota própria a outra pessoa', function () {
    foreach (range(1, 20) as $envio) {
        postDeposito();
    }

    postDeposito()->assertSessionHasErrors(['limite']);

    $this->actingAs(userWithWallet());

    postDeposito()
        ->assertRedirect(route('dashboard'))
        ->assertSessionDoesntHaveErrors(['limite']);
});

test('não gasta a cota financeira com leituras nem com o logout', function () {
    $deposito = deposit($this->user, '10,00');

    foreach (range(1, 8) as $leitura) {
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('statement'))->assertOk();
        $this->get(route('statement', ['page' => 2]))->assertOk();
        $this->get(route('deposits.create'))->assertOk();
        $this->get(route('transfers.create'))->assertOk();
    }

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->actingAs($this->user);

    // Quarenta e uma requisicoes depois, a cota financeira continua inteira.
    foreach (range(1, 20) as $envio) {
        postDeposito()->assertRedirect(route('dashboard'));
    }

    postDeposito()->assertSessionHasErrors(['limite']);

    expect($deposito->refresh()->status)->toBe(TransactionStatus::Completed);
});
