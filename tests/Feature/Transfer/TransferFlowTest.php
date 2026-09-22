<?php

use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

it('keeps a visitor away from both the form and the submission', function () {
    $this->get(route('transfers.create'))->assertRedirect(route('login'));

    $this->post(route('transfers.store'), [
        'recipient_email' => 'alguem@exemplo.com',
        'amount' => '10,00',
    ])->assertRedirect(route('login'));

    expect(Transaction::count())->toBe(0);
});

it('serves a form with csrf and a server made key', function () {
    $response = $this->actingAs(userWithWallet())->get(route('transfers.create'));

    $response->assertOk();

    $csrf = preg_match('/name="_token" value="([^"]+)"/', $response->getContent(), $token) === 1;

    expect($csrf)->toBeTrue()
        ->and($token[1])->toBe(csrf_token())
        ->and(hiddenKey($response->getContent()))->toMatch(UUID_V7);
});

it('gives a different key to each visit of the form', function () {
    $this->actingAs(userWithWallet());

    $primeira = hiddenKey($this->get(route('transfers.create'))->getContent());
    $segunda = hiddenKey($this->get(route('transfers.create'))->getContent());

    expect($primeira)->not->toBe($segunda);
});

it('keeps a valid key when the form comes back with an error', function () {
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

it('replaces a key that did not come back as a uuid', function (string $enviada) {
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
    'quase um uuid' => '0199c0ff-ee00-7000-8000-00000000000',
]);

it('refuses a submission that carries no key at all', function () {
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

it('refuses an invalid recipient email with a message and no transaction', function (string $email) {
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

it('refuses an invalid amount with a message and no transaction', function (string $amount) {
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

it('refuses a key that is not a uuid', function () {
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

it('shows a recipient that does not exist on the email field and changes nothing', function () {
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

it('shows a transfer to yourself on the email field and changes nothing', function () {
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

it('shows a missing balance on the amount field and changes nothing', function () {
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

it('keeps database detail out of the screen when a refusal happens', function () {
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

it('answers a successful transfer with a redirect and a message', function () {
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

it('does not transfer again when the page after the redirect is reloaded', function () {
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

it('reaches the transfer form from the dashboard', function () {
    $this->actingAs(userWithWallet())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('transfers.create'), false);
});
