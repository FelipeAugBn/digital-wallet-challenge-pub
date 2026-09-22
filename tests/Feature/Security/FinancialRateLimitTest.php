<?php

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = usuarioComCotaLimpa();
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
    $bia = usuarioComCotaLimpa();
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

    $this->actingAs(usuarioComCotaLimpa());

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

test('mantém o visitante fora das rotas financeiras, antes mesmo da cota', function () {
    $original = deposit($this->user, '10,00');

    $this->post(route('logout'));

    // A autenticacao corre antes do limite, entao o visitante nem chega a
    // gastar cota — nem a propria, que nao existe, nem a de outra pessoa.
    postDeposito()->assertRedirect(route('login'));
    postTransferencia($this->user->email)->assertRedirect(route('login'));
    $this->post(route('reversals.store', $original))->assertRedirect(route('login'));

    expect(Transaction::count())->toBe(1)
        ->and($original->refresh()->status)->toBe(TransactionStatus::Completed);
});
