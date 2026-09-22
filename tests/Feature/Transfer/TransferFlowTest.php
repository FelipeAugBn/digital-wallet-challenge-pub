<?php

use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('mantém o visitante fora do formulário e do envio', function () {
    $this->get(route('transfers.create'))->assertRedirect(route('login'));

    $this->post(route('transfers.store'), [
        'recipient_email' => 'alguem@exemplo.com',
        'amount' => '10,00',
    ])->assertRedirect(route('login'));

    expect(Transaction::count())->toBe(0);
});

test('entrega o formulário com CSRF e chave gerada no servidor', function () {
    $response = $this->actingAs(userWithWallet())->get(route('transfers.create'));

    $response->assertOk();

    $csrf = preg_match('/name="_token" value="([^"]+)"/', $response->getContent(), $token) === 1;

    expect($csrf)->toBeTrue()
        ->and($token[1])->toBe(csrf_token())
        ->and(hiddenKey($response->getContent()))->toMatch(UUID_V7);
});

test('dá uma chave diferente a cada visita ao formulário', function () {
    $this->actingAs(userWithWallet());

    $primeira = hiddenKey($this->get(route('transfers.create'))->getContent());
    $segunda = hiddenKey($this->get(route('transfers.create'))->getContent());

    expect($primeira)->not->toBe($segunda);
});

test('mantém a chave válida quando o formulário volta com erro', function () {
    $key = (string) Str::uuid7();

    $this->actingAs(userWithWallet())
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => 'alguem@exemplo.com',
            'amount' => 'abc',
            'idempotency_key' => $key,
        ])
        ->assertSessionHasErrors('amount');

    // A chave antiga volta, entao corrigir o valor continua sendo a mesma
    // operacao e nao uma segunda.
    expect(hiddenKey($this->get(route('transfers.create'))->getContent()))->toBe($key);
});

test('substitui a chave que não voltou como UUID', function (string $enviada) {
    $this->actingAs(userWithWallet())
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => 'alguem@exemplo.com',
            'amount' => 'abc',
            'idempotency_key' => $enviada,
        ])
        ->assertSessionHasErrors('amount');

    $nova = hiddenKey($this->get(route('transfers.create'))->getContent());

    // O que veio do navegador nao pode virar chave: o servidor gera outra.
    expect($nova)->not->toBe($enviada)->toMatch(UUID_V7);
})->with([
    'texto qualquer' => 'chave-qualquer',
    'vazia' => '',
    'quase um UUID' => '0199c0ff-ee00-7000-8000-00000000000',
]);

test('recusa envio que não traz chave nenhuma', function () {
    $this->actingAs(userWithWallet())
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => 'alguem@exemplo.com',
            'amount' => '10,00',
        ])
        ->assertSessionHasErrors('idempotency_key');

    expect(Transaction::count())->toBe(0)
        ->and(hiddenKey($this->get(route('transfers.create'))->getContent()))->toMatch(UUID_V7);
});

test('recusa e-mail de destinatário inválido com mensagem e sem criar transação', function (string $email) {
    $ana = userWithWallet(5_000);

    $response = $this->actingAs($ana)
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => $email,
            'amount' => '10,00',
            'idempotency_key' => (string) Str::uuid7(),
        ]);

    $response->assertRedirect(route('transfers.create'))->assertSessionHasErrors('recipient_email');

    expect(Transaction::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
})->with(['', 'sem-arroba', 'pessoa@', '@exemplo.com', 'pessoa exemplo@teste.com']);

test('recusa valor inválido com mensagem e sem criar transação', function (string $amount) {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    $response = $this->actingAs($ana)
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => $bia->email,
            'amount' => $amount,
            'idempotency_key' => (string) Str::uuid7(),
        ]);

    $response->assertRedirect(route('transfers.create'))->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
})->with(['', '0', '-10', '1000.50', '10,505', '1.000.000,01', 'abc']);

test('recusa chave que não é UUID', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    $this->actingAs($ana)
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => $bia->email,
            'amount' => '10,00',
            'idempotency_key' => 'chave-qualquer',
        ])
        ->assertSessionHasErrors('idempotency_key');

    expect(Transaction::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

test('mostra destinatário inexistente no campo de e-mail e não altera nada', function () {
    $ana = userWithWallet(5_000);

    $response = $this->actingAs($ana)
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => 'ninguem@exemplo.com',
            'amount' => '10,00',
            'idempotency_key' => (string) Str::uuid7(),
        ]);

    $response->assertRedirect(route('transfers.create'))
        ->assertSessionHasErrors(['recipient_email' => 'Não encontramos ninguém com esse e-mail.']);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

test('mostra transferência para si mesmo no campo de e-mail e não altera nada', function () {
    $ana = userWithWallet(5_000);

    $response = $this->actingAs($ana)
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => $ana->email,
            'amount' => '10,00',
            'idempotency_key' => (string) Str::uuid7(),
        ]);

    $response->assertRedirect(route('transfers.create'))
        ->assertSessionHasErrors(['recipient_email' => 'Você não pode transferir para a sua própria carteira.']);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

test('mostra saldo insuficiente no campo de valor e não altera nada', function () {
    $ana = userWithWallet(1_000);
    $bia = userWithWallet(500);

    $response = $this->actingAs($ana)
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => $bia->email,
            'amount' => '30,00',
            'idempotency_key' => (string) Str::uuid7(),
        ]);

    $response->assertRedirect(route('transfers.create'))
        ->assertSessionHasErrors(['amount' => 'Saldo insuficiente para esta transferência.']);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(1_000)
        ->and(walletOf($bia)->balance)->toBe(500);
});

test('mantém detalhe do banco fora da tela quando há uma recusa', function () {
    $ana = userWithWallet(1_000);
    $bia = userWithWallet();

    $this->actingAs($ana)
        ->from(route('transfers.create'))
        ->post(route('transfers.store'), [
            'recipient_email' => $bia->email,
            'amount' => '30,00',
            'idempotency_key' => (string) Str::uuid7(),
        ]);

    $conteudo = $this->get(route('transfers.create'))->getContent();

    // A tela mostra a recusa em portugues e nada do que existe por baixo dela.
    expect($conteudo)->toContain('Saldo insuficiente para esta transferência.')
        ->not->toContain('SQLSTATE')
        ->not->toContain('for update')
        ->not->toContain('lockForUpdate')
        ->not->toContain('App\\Actions')
        ->not->toContain('wallet_id');
});

test('responde à transferência bem-sucedida com redirect e mensagem', function () {
    $ana = userWithWallet(500_000);
    $bia = userWithWallet();

    $response = $this->actingAs($ana)->post(route('transfers.store'), [
        'recipient_email' => $bia->email,
        'amount' => '1.000,50',
        'idempotency_key' => (string) Str::uuid7(),
    ]);

    $response->assertRedirect(route('dashboard'))
        ->assertSessionHas('sucesso', 'Transferência de R$ 1.000,50 para '.$bia->email.' realizada.');

    $this->get(route('dashboard'))->assertOk()->assertSee('Transferência de R$ 1.000,50 para '.$bia->email.' realizada.');

    expect(walletOf($ana)->balance)->toBe(399_950)
        ->and(walletOf($bia)->balance)->toBe(100_050);
});

test('não transfere de novo quando a página do redirect é recarregada', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    $this->actingAs($ana)
        ->post(route('transfers.store'), [
            'recipient_email' => $bia->email,
            'amount' => '30,00',
            'idempotency_key' => (string) Str::uuid7(),
        ])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('dashboard'))->assertOk();

    expect(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

test('chega ao formulário de transferência pelo painel', function () {
    $this->actingAs(userWithWallet())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('transfers.create'), false);
});
