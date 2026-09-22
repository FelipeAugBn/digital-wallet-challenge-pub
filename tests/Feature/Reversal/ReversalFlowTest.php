<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;

require_once __DIR__.'/helpers.php';

test('mantém o visitante fora do POST de estorno', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $this->post(route('reversals.store', $deposito))->assertRedirect(route('login'));

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(1_000);
});

test('estorna o próprio depósito pela rota, com redirect e mensagem', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $this->actingAs($ana)
        ->from(route('statement'))
        ->post(route('reversals.store', $deposito))
        ->assertRedirect(route('statement'))
        ->assertSessionHas('sucesso', 'Estorno de R$ 10,00 realizado.');

    expect(walletOf($ana)->balance)->toBe(0)
        ->and(Transaction::query()->where('type', TransactionType::Reversal)->count())->toBe(1);

    $this->get(route('statement'))->assertOk()->assertSee('Estorno de R$ 10,00 realizado.');
});

test('estorna pela rota a transferência que enviou', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    $this->actingAs($ana)
        ->from(route('statement'))
        ->post(route('reversals.store', $transferencia))
        ->assertRedirect(route('statement'));

    expect(walletOf($ana)->balance)->toBe(5_000)
        ->and(walletOf($bia)->balance)->toBe(0);
});

test('recusa o estorno pedido por quem recebeu a transferência', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    $this->actingAs($bia)->post(route('reversals.store', $transferencia))->assertForbidden();

    expect(Transaction::query()->whereKey($transferencia->id)->sole()->status)->toBe(TransactionStatus::Completed)
        ->and(walletOf($bia)->balance)->toBe(3_000)
        ->and(Transaction::count())->toBe(1);
});

test('recusa o estorno pedido por pessoa alheia à operação', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $carol = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    $this->actingAs($carol)->post(route('reversals.store', $transferencia))->assertForbidden();

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

test('recusa o POST de estorno de um estorno', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $this->actingAs($ana)->from(route('statement'))->post(route('reversals.store', $deposito));

    $estorno = Transaction::query()->where('type', TransactionType::Reversal)->sole();

    $this->actingAs($ana)->post(route('reversals.store', $estorno))->assertForbidden();

    expect(Transaction::count())->toBe(2);
});

test('avisa em português quando a operação já foi estornada', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $this->actingAs($ana)->from(route('statement'))->post(route('reversals.store', $deposito));

    // A segunda tentativa vem de uma aba que ainda mostrava o botão.
    $this->from(route('statement'))
        ->post(route('reversals.store', $deposito))
        ->assertRedirect(route('statement'))
        ->assertSessionHasErrors(['estorno' => 'Esta operação já foi estornada.']);

    expect(Transaction::query()->where('type', TransactionType::Reversal)->count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(0);

    $this->get(route('statement'))->assertOk()->assertSee('Esta operação já foi estornada.');
});

test('não expõe SQLSTATE, classe nem detalhe interno na recusa', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $this->actingAs($ana)->from(route('statement'))->post(route('reversals.store', $deposito));
    $this->from(route('statement'))->post(route('reversals.store', $deposito));

    $html = $this->get(route('statement'))->getContent();

    expect($html)->not->toContain('23505')
        ->and($html)->not->toContain('SQLSTATE')
        ->and($html)->not->toContain('App\\')
        ->and($html)->not->toContain('for update');
});

test('oferece o botão de estorno no extrato para a operação que iniciou', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    expect(temBotaoDeEstorno($html, $deposito))->toBeTrue()
        ->and($html)->toContain('Estornar');
});

test('leva o CSRF no formulário de estorno', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    expect(preg_match('/<form class="estorno".*?name="_token" value="([^"]+)"/s', $html, $achado))->toBe(1)
        ->and($achado[1])->toBe(csrf_token());
});

test('não oferece o botão para a transferência recebida', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    $daBia = $this->actingAs($bia)->get(route('statement'))->getContent();
    $daAna = $this->actingAs($ana)->get(route('statement'))->getContent();

    expect(temBotaoDeEstorno($daBia, $transferencia))->toBeFalse()
        ->and($daBia)->not->toContain('Estornar')
        ->and(temBotaoDeEstorno($daAna, $transferencia))->toBeTrue();
});

test('some com o botão depois que a operação é estornada', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');
    $estorno = reverse($deposito, $ana);

    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    // Nem a operação já estornada nem o próprio estorno podem ser estornados.
    expect(temBotaoDeEstorno($html, $deposito))->toBeFalse()
        ->and(temBotaoDeEstorno($html, $estorno))->toBeFalse()
        ->and($html)->not->toContain('Estornar');
});

test('não mostra o botão de estorno no painel', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    $this->actingAs($ana)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Depósito')
        ->assertDontSee('Estornar');
});

test('mantém original e estorno visíveis no extrato depois do estorno real', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $ana->update(['name' => 'Ana Ribeiro']);
    $bia->update(['name' => 'Bia Costa']);

    $transferencia = transfer($ana, $bia, '30,00');

    $this->actingAs($ana)->from(route('statement'))->post(route('reversals.store', $transferencia));

    $daAna = $this->get(route('statement'))->getContent();

    expect($daAna)->toContain('Transferência enviada para Bia Costa')
        ->and($daAna)->toContain('Estorno de transferência enviada para Bia Costa')
        ->and(valoresNaTela($daAna))->toBe(['+R$ 30,00', '-R$ 30,00'])
        ->and(substr_count($daAna, 'Estornada'))->toBe(1);
});

test('mostra o rótulo exigido pela RF-06 no débito de quem recebeu', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $ana->update(['name' => 'Ana Ribeiro']);

    $transferencia = transfer($ana, $bia, '30,00');

    $this->actingAs($ana)->from(route('statement'))->post(route('reversals.store', $transferencia));

    $daBia = $this->actingAs($bia)->get(route('statement'))->getContent();

    expect($daBia)->toContain('Estorno de transferência recebida de Ana Ribeiro')
        ->and(valoresNaTela($daBia))->toBe(['-R$ 30,00', '+R$ 30,00']);
});
