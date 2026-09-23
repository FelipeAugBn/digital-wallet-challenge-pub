<?php

use Illuminate\Support\Carbon;

require_once __DIR__.'/helpers.php';

// O relógio é fixado por teste e devolvido no fim, mesmo quando a asserção falha.
afterEach(fn () => Carbon::setTestNow());

/** Um depósito feito no instante dado, como se a pessoa o tivesse feito naquela hora. */
function depositoEm(string $quando, $pessoa, string $valor = '1,00'): void
{
    Carbon::setTestNow($quando);
    deposit($pessoa, $valor);
}

test('chama de Hoje e Ontem os dois dias mais recentes', function () {
    $ana = userWithWallet();
    depositoEm('2026-09-22 10:00:00', $ana);
    depositoEm('2026-09-23 09:00:00', $ana);

    Carbon::setTestNow('2026-09-23 12:00:00');

    expect(diasNaTela($this->actingAs($ana)->get(route('statement'))->getContent()))->toBe(['Hoje', 'Ontem']);
});

test('mostra dia e mês por extenso nas datas mais antigas', function () {
    $ana = userWithWallet();
    depositoEm('2026-09-05 15:40:00', $ana);

    Carbon::setTestNow('2026-09-23 12:00:00');

    expect(diasNaTela($this->actingAs($ana)->get(route('statement'))->getContent()))->toBe(['5 de setembro']);
});

test('acrescenta o ano quando a data é de outro ano', function () {
    $ana = userWithWallet();
    depositoEm('2025-12-30 08:15:00', $ana);

    Carbon::setTestNow('2026-09-23 12:00:00');

    expect(diasNaTela($this->actingAs($ana)->get(route('statement'))->getContent()))->toBe(['30 de dezembro de 2025']);
});

test('lançamentos do mesmo dia ficam sob um único cabeçalho', function () {
    $ana = userWithWallet();
    depositoEm('2026-09-10 08:00:00', $ana, '1,00');
    depositoEm('2026-09-10 13:30:00', $ana, '2,00');
    depositoEm('2026-09-10 19:45:00', $ana, '3,00');

    Carbon::setTestNow('2026-09-23 12:00:00');
    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    expect(diasNaTela($html))->toBe(['10 de setembro'])
        ->and(valoresNaTela($html))->toHaveCount(3)
        // A hora fica na linha, já que o dia está no cabeçalho.
        ->and($html)->toContain('19:45')->toContain('13:30')->toContain('08:00');
});

test('a ordem dos cabeçalhos acompanha a ordem dos lançamentos', function () {
    $ana = userWithWallet();
    depositoEm('2025-12-30 08:15:00', $ana);
    depositoEm('2026-09-05 15:40:00', $ana);
    depositoEm('2026-09-23 09:00:00', $ana);

    Carbon::setTestNow('2026-09-23 12:00:00');

    expect(diasNaTela($this->actingAs($ana)->get(route('statement'))->getContent()))
        ->toBe(['Hoje', '5 de setembro', '30 de dezembro de 2025']);
});
