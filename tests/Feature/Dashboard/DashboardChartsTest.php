<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/helpers.php';

/** O "hoje" destes testes: com ele fixo, cada janela e cada mês são conhecidos. */
const HOJE_DOS_GRAFICOS = '2026-09-23 12:00:00';

// Os testes movem o relógio para datar cada operação; ele volta no fim, mesmo
// quando uma asserção falha.
afterEach(fn () => Carbon::setTestNow());

/** Posiciona o relógio para que a próxima operação aconteça nesse instante. */
function relogioEm(string $quando): void
{
    Carbon::setTestNow($quando);
}

/** Abre o painel da pessoa no "hoje" dos gráficos. */
function painelDe(User $pessoa): TestResponse
{
    Carbon::setTestNow(HOJE_DOS_GRAFICOS);

    return test()->actingAs($pessoa)->get(route('dashboard'))->assertOk();
}

test('não desenha gráficos sem movimentação', function () {
    painelDe(userWithWallet())
        ->assertDontSee('Saldo nas últimas cinco semanas')
        ->assertDontSee('Sem movimentação nas últimas cinco semanas.');
});

test('o anel resume o mês corrente, com a porcentagem no centro e os valores na legenda', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    relogioEm('2026-09-03 10:00:00');
    deposit($ana, '25,00');
    relogioEm('2026-09-10 15:30:00');
    transfer($ana, $bia, '10,00');

    $html = painelDe($ana)->assertSee('Setembro')->getContent();

    // 25 de 35 entrou: 71%. O centro nunca cresce; o que cresce vai para a legenda.
    expect(centroDoAnel($html))->toBe(['71%', 'entrou'])
        ->and(legendaDoAnel($html))->toBe([
            'Entrou' => '+R$ 25,00',
            'Saiu' => '-R$ 10,00',
            'Estornado' => 'R$ 0,00',
            'Líquido' => '+R$ 15,00',
        ]);
});

test('o estorno entra no anel e marca o dia na curva e nas barras', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    relogioEm('2026-09-03 10:00:00');
    deposit($ana, '25,00');
    relogioEm('2026-09-10 15:30:00');
    $transferencia = transfer($ana, $bia, '10,00');
    relogioEm('2026-09-11 09:00:00');
    reverseDirectly($transferencia);

    $html = painelDe($ana)->getContent();

    // O estorno devolve os R$ 10,00: entrou 35, saiu 10, e a saída ficou marcada como estornada.
    expect(centroDoAnel($html))->toBe(['78%', 'entrou'])
        ->and(legendaDoAnel($html))->toBe([
            'Entrou' => '+R$ 35,00',
            'Saiu' => '-R$ 10,00',
            'Estornado' => 'R$ 10,00',
            'Líquido' => '+R$ 25,00',
        ])
        ->and($html)->toContain('class="marca-estorno"')
        ->and($html)->toContain('class="b-estorno"')
        ->and($html)->toContain('O ponto âmbar marca um estorno.');
});

test('num dia com entrada, saída e estorno, a barra mostra só a parte que foi estorno', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    relogioEm('2026-09-10 08:00:00');
    deposit($ana, '50,00');
    relogioEm('2026-09-10 09:00:00');
    $transferencia = transfer($ana, $bia, '10,00');

    // No dia 11: um depósito comum de R$ 30,00, o estorno que devolve R$ 10,00
    // e uma transferência comum de R$ 5,00. Entrou 40, dos quais 10 de estorno.
    relogioEm('2026-09-11 08:00:00');
    deposit($ana, '30,00');
    relogioEm('2026-09-11 09:00:00');
    reverseDirectly($transferencia);
    relogioEm('2026-09-11 10:00:00');
    transfer($ana, $bia, '5,00');

    $html = painelDe($ana)->getContent();

    // A escala é a maior barra, os R$ 50,00 do dia 10 (128 unidades). A barra
    // verde do dia 11 tem 40, ou 102,4; um quarto dela, na ponta, é âmbar: 25,6.
    expect(descricaoDoGrafico($html, 'barras-desc'))
        ->toBe('10 set: entrou R$ 50,00, saiu R$ 10,00 (R$ 10,00 de estorno); 11 set: entrou R$ 40,00 (R$ 10,00 de estorno), saiu R$ 5,00.')
        ->and(substr_count($html, 'class="b-entrada"'))->toBe(2)
        ->and(substr_count($html, 'class="b-saida"'))->toBe(2)
        ->and(substr_count($html, 'class="b-estorno"'))->toBe(2)
        ->and($html)->toMatch('/height="25\.6" clip-path="url\(#ce1\)" class="b-estorno"/');
});

test('o estorno conta no mês em que o dinheiro voltou, não no mês da operação desfeita', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    // Agosto: a transferência sai. Setembro: o estorno a desfaz e o dinheiro volta.
    relogioEm('2026-08-24 09:00:00');
    deposit($ana, '100,00');
    relogioEm('2026-08-25 09:00:00');
    $transferencia = transfer($ana, $bia, '40,00');
    relogioEm('2026-09-05 09:00:00');
    reverseDirectly($transferencia);

    $html = painelDe($ana)->getContent();

    // Contar pelo estado da operação original deixaria setembro com R$ 0,00
    // estornado, embora tenha sido o mês em que os R$ 40,00 voltaram.
    expect(legendaDoAnel($html))->toBe([
        'Entrou' => '+R$ 40,00',
        'Saiu' => 'R$ 0,00',
        'Estornado' => 'R$ 40,00',
        'Líquido' => '+R$ 40,00',
    ])
        // Os dois dias ficam marcados: o da operação desfeita e o do estorno.
        ->and(substr_count($html, 'class="b-estorno"'))->toBe(2)
        ->and(descricaoDoGrafico($html, 'barras-desc'))
        ->toBe('24 ago: entrou R$ 100,00, saiu R$ 0,00; 25 ago: entrou R$ 0,00, saiu R$ 40,00 (R$ 40,00 de estorno); 5 set: entrou R$ 40,00 (R$ 40,00 de estorno), saiu R$ 0,00.');
});

test('cada painel desenha apenas os lançamentos da própria carteira', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    relogioEm('2026-09-10 09:00:00');
    deposit($ana, '12,34');
    relogioEm('2026-09-11 09:00:00');
    deposit($bia, '99,99');

    $daAna = painelDe($ana)->getContent();
    $daBia = painelDe($bia)->getContent();

    // Cada valor só existe numa das carteiras: nenhum atravessa para o outro painel.
    expect($daAna)->toContain('12,34')->not->toContain('99,99')
        ->and($daBia)->toContain('99,99')->not->toContain('12,34')
        ->and(rotulosDasBarras($daAna))->toBe(['10 set'])
        ->and(rotulosDasBarras($daBia))->toBe(['11 set'])
        ->and(descricaoDoGrafico($daAna, 'curva-desc'))->toBe('O saldo era R$ 0,00 em 19 de agosto e termina em R$ 12,34 hoje.')
        ->and(descricaoDoGrafico($daBia, 'curva-desc'))->toBe('O saldo era R$ 0,00 em 19 de agosto e termina em R$ 99,99 hoje.')
        ->and(legendaDoAnel($daAna)['Entrou'])->toBe('+R$ 12,34')
        ->and(legendaDoAnel($daBia)['Entrou'])->toBe('+R$ 99,99');
});

test('só as últimas cinco semanas entram nas barras, e a curva parte do saldo anterior', function () {
    $ana = userWithWallet();

    relogioEm('2026-08-01 09:00:00');
    deposit($ana, '100,00');
    relogioEm('2026-09-20 09:00:00');
    deposit($ana, '5,00');

    $html = painelDe($ana)->getContent();

    // 23 de setembro menos 35 dias é 19 de agosto: o depósito de 1º de agosto
    // não vira barra, mas o saldo que ele deixou é de onde a curva começa.
    expect(rotulosDasBarras($html))->toBe(['20 set'])
        ->and(descricaoDoGrafico($html, 'curva-desc'))->toBe('O saldo era R$ 100,00 em 19 de agosto e termina em R$ 105,00 hoje.')
        ->and(descricaoDoGrafico($html, 'barras-desc'))->toBe('20 set: entrou R$ 5,00, saiu R$ 0,00.');
});

test('avisa quando toda a movimentação é mais antiga que a janela', function () {
    $ana = userWithWallet();

    relogioEm('2026-08-01 09:00:00');
    deposit($ana, '100,00');

    painelDe($ana)
        ->assertDontSee('Saldo nas últimas cinco semanas')
        ->assertSee('Sem movimentação nas últimas cinco semanas.');
});

test('o mês sem movimento mostra o anel vazio, sem dividir por zero', function () {
    $ana = userWithWallet();

    relogioEm('2026-08-25 09:00:00');
    deposit($ana, '40,00');

    $html = painelDe($ana)->getContent();

    expect(centroDoAnel($html))->toBe(['—', 'sem movimento'])
        ->and(legendaDoAnel($html)['Líquido'])->toBe('R$ 0,00')
        ->and($html)->not->toContain('class="seg-entrou"')
        ->and(rotulosDasBarras($html))->toBe(['25 ago']);
});

test('com muitos dias, só alguns ganham rótulo, sem perder barra', function () {
    $ana = userWithWallet();

    // Doze dias seguidos com movimento: doze duplas de barras, seis rótulos.
    for ($dia = 1; $dia <= 12; $dia++) {
        relogioEm(sprintf('2026-09-%02d 10:00:00', $dia));
        deposit($ana, '1,00');
    }

    $html = painelDe($ana)->getContent();

    expect(rotulosDasBarras($html))->toBe(['1 set', '3 set', '5 set', '7 set', '9 set', '11 set'])
        ->and(substr_count($html, 'class="b-entrada"'))->toBe(12);
});

test('a curva termina no saldo oficial da carteira, não na soma dos lançamentos', function () {
    $ana = userWithWallet();

    relogioEm('2026-09-20 09:00:00');
    deposit($ana, '10,00');

    // Mexo no saldo por fora: a curva precisa terminar no oficial.
    Wallet::query()->whereKey(walletOf($ana)->id)->update(['balance' => 9_900]);

    expect(descricaoDoGrafico(painelDe($ana)->getContent(), 'curva-desc'))
        ->toBe('O saldo era R$ 0,00 em 19 de agosto e termina em R$ 99,00 hoje.');
});
