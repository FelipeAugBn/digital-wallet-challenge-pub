<?php

use Illuminate\Support\Carbon;

require_once __DIR__.'/helpers.php';

// Os testes movem o relógio para datar cada operação; ele volta no fim, mesmo
// quando uma asserção falha.
afterEach(fn () => Carbon::setTestNow());

/**
 * As leituras ao lado do saldo, rótulo a valor.
 *
 * @return array<string, string>
 */
function leiturasDoPainel(string $html): array
{
    preg_match_all('/<dt>([^<]+)<\/dt><dd class="[a-z]+">([^<]+)<\/dd>/', $html, $achados, PREG_SET_ORDER);

    return array_combine(array_column($achados, 1), array_column($achados, 2));
}

test('as leituras dizem o que mudou na semana e no mês', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    // Setembro inteiro conta no mês; só o que aconteceu de 16 em diante conta na semana.
    Carbon::setTestNow('2026-09-10 10:00:00');
    deposit($ana, '25,00');
    Carbon::setTestNow('2026-09-20 15:30:00');
    transfer($ana, $bia, '10,00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $html = $this->actingAs($ana)->get(route('dashboard'))->assertOk()->getContent();

    expect(leiturasDoPainel($html))->toBe(['7 dias' => '-R$ 10,00', 'setembro' => '+R$ 15,00'])
        ->and($html)->toContain('<dd class="negativo">-R$ 10,00</dd>')
        ->and($html)->toContain('<dd class="positivo">+R$ 15,00</dd>');
});

test('a fita mostra o saldo que ficou depois de cada movimentação', function () {
    $ana = userWithWallet();

    Carbon::setTestNow('2026-09-20 09:00:00');
    deposit($ana, '10,00');
    Carbon::setTestNow('2026-09-21 09:00:00');
    deposit($ana, '5,00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $html = $this->actingAs($ana)->get(route('dashboard'))->assertOk()->getContent();
    preg_match_all('/<span class="pos"><span class="pos-rotulo">saldo<\/span> ([^<]+)<\/span>/', $html, $saldos);

    // Da mais recente para a mais antiga: R$ 15,00 depois do segundo depósito, R$ 10,00 depois do primeiro.
    expect($saldos[1])->toBe(['R$ 15,00', 'R$ 10,00']);
});

test('o tema claro é o padrão e o botão oferece o escuro', function () {
    $ana = userWithWallet();

    foreach ([route('login'), null] as $caminho) {
        $tela = $caminho ? $this->get($caminho) : $this->actingAs($ana)->get(route('dashboard'));
        $html = $tela->assertOk()->getContent();

        // Nada no servidor fixa tema: o documento nasce claro, e o botão
        // existe em toda tela com o mesmo texto para leitor de tela.
        expect($html)->toContain('<html lang="pt-BR">')
            ->and($html)->toContain('id="tema"')
            ->and($html)->toContain('aria-label="Ativar tema escuro"');
    }
});

test('a aba do navegador leva o ícone da Wallet, em SVG e com reserva para quem não o lê', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    // O SVG e o preferido, o .ico fica para navegador antigo e o de toque para
    // a tela inicial do celular. Os tres arquivos existem de verdade em `public`.
    // O endereco vem do APP_URL do ambiente, por isso so o caminho e fixado.
    expect($html)->toMatch('#<link rel="icon" href="[^"]+/favicon\.svg" type="image/svg\+xml">#')
        ->and($html)->toMatch('#<link rel="icon" href="[^"]+/favicon\.ico" sizes="32x32">#')
        ->and($html)->toMatch('#<link rel="apple-touch-icon" href="[^"]+/apple-touch-icon\.png">#')
        ->and(file_get_contents(public_path('favicon.svg')))->toContain('<svg')
        ->and(filesize(public_path('favicon.ico')))->toBeGreaterThan(0)
        ->and(filesize(public_path('apple-touch-icon.png')))->toBeGreaterThan(0);
});
