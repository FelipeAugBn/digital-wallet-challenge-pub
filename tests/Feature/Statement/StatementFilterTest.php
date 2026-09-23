<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

// O relógio é fixado por teste e devolvido no fim, mesmo quando a asserção falha.
afterEach(fn () => Carbon::setTestNow());

/**
 * A consulta que o link da próxima página leva na URL.
 *
 * Ler a URL inteira, e não só procurar o texto do período nela, é o que prova
 * as duas metades da promessa: o filtro continua e nada além dele entra.
 *
 * @return array<string, string>
 */
function consultaDaProximaPagina(string $html): array
{
    preg_match('/<a href="([^"]+)" rel="next"/', $html, $achado);
    parse_str((string) parse_url(html_entity_decode($achado[1] ?? ''), PHP_URL_QUERY), $consulta);

    return $consulta;
}

/** Um depósito por dia de setembro, de 1 centavo no dia 1 até `$dias` no dia `$dias`. */
function depositosDiariosDeSetembro(User $pessoa, int $dias): void
{
    foreach (range(1, $dias) as $dia) {
        depositoEm(sprintf('2026-09-%02d 10:00:00', $dia), $pessoa, centavosComoTexto($dia));
    }
}

test('sem filtro, o extrato continua mostrando a carteira inteira', function () {
    $ana = userWithWallet();
    depositoEm('2026-07-04 08:00:00', $ana, '1,00');
    depositoEm('2026-09-10 10:00:00', $ana, '2,00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    expect(valoresNaTela($html))->toBe(['+R$ 2,00', '+R$ 1,00'])
        // Sem filtro não há o que limpar nos links, nem no estado vazio.
        ->and(consultaDaProximaPagina($html))->toBe([]);
});

test('a data inicial vale a partir do começo do dia informado', function () {
    $ana = userWithWallet();
    depositoEm('2026-09-09 23:59:59', $ana, '1,00');
    depositoEm('2026-09-10 00:00:00', $ana, '2,00');
    depositoEm('2026-09-10 08:30:00', $ana, '3,00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $html = $this->actingAs($ana)->get(route('statement', ['inicio' => '2026-09-10']))->getContent();

    // A meia-noite do dia 10 entra; o último instante do dia 9 fica fora.
    expect(valoresNaTela($html))->toBe(['+R$ 3,00', '+R$ 2,00']);
});

test('a data final inclui o dia inteiro e para antes do dia seguinte', function () {
    $ana = userWithWallet();
    depositoEm('2026-09-10 00:00:00', $ana, '1,00');
    depositoEm('2026-09-10 23:59:59', $ana, '2,00');
    depositoEm('2026-09-11 00:00:00', $ana, '3,00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $html = $this->actingAs($ana)->get(route('statement', ['fim' => '2026-09-10']))->getContent();

    // O limite é a meia-noite do dia 11, e ela é exclusiva: o dia 10 cabe
    // inteiro, até o último instante que a coluna sabe guardar.
    expect(valoresNaTela($html))->toBe(['+R$ 2,00', '+R$ 1,00']);
});

test('o período completo devolve somente o que está dentro dele', function () {
    $ana = userWithWallet();
    depositoEm('2026-08-31 23:59:59', $ana, '1,00');
    depositoEm('2026-09-01 00:00:00', $ana, '2,00');
    depositoEm('2026-09-15 12:00:00', $ana, '3,00');
    depositoEm('2026-09-30 23:59:59', $ana, '4,00');
    depositoEm('2026-10-01 00:00:00', $ana, '5,00');
    Carbon::setTestNow('2026-10-05 12:00:00');

    $html = $this->actingAs($ana)
        ->get(route('statement', ['inicio' => '2026-09-01', 'fim' => '2026-09-30']))
        ->getContent();

    expect(valoresNaTela($html))->toBe(['+R$ 4,00', '+R$ 3,00', '+R$ 2,00']);
});

test('recusa a data final anterior à inicial', function () {
    $this->actingAs(userWithWallet())
        ->get(route('statement', ['inicio' => '2026-09-10', 'fim' => '2026-09-09']))
        ->assertRedirect(route('statement'))
        ->assertSessionHasErrors(['fim' => 'A data final não pode ser anterior à data inicial.']);
});

test('aceita a data final igual à inicial', function () {
    $ana = userWithWallet();
    depositoEm('2026-09-10 12:00:00', $ana, '1,00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $html = $this->actingAs($ana)
        ->get(route('statement', ['inicio' => '2026-09-10', 'fim' => '2026-09-10']))
        ->assertOk()
        ->getContent();

    expect(valoresNaTela($html))->toBe(['+R$ 1,00']);
});

test('recusa datas fora do formato, uma mensagem por campo', function () {
    $this->actingAs(userWithWallet())
        ->get(route('statement', ['inicio' => '10/09/2026', 'fim' => '2026-02-30']))
        ->assertRedirect(route('statement'))
        ->assertSessionHasErrors([
            'inicio' => 'Informe a data inicial no formato AAAA-MM-DD.',
            'fim' => 'Informe a data final no formato AAAA-MM-DD.',
        ]);
});

test('uma data inicial ilegível não acusa também a data final', function () {
    $this->actingAs(userWithWallet())
        ->get(route('statement', ['inicio' => 'ontem', 'fim' => '2026-09-10']))
        ->assertRedirect(route('statement'))
        ->assertSessionHasErrors('inicio')
        ->assertSessionDoesntHaveErrors('fim');
});

test('a mensagem da recusa aparece no extrato, com as datas de volta no formulário', function () {
    $this->actingAs(userWithWallet())
        ->get(route('statement', ['inicio' => '2026-09-10', 'fim' => '2026-09-09']))
        ->assertRedirect(route('statement'));

    $html = $this->get(route('statement'))->assertOk()->getContent();

    expect($html)->toContain('A data final não pode ser anterior à data inicial.')
        ->and($html)->toContain('name="inicio" type="date" value="2026-09-10"')
        ->and($html)->toContain('name="fim" type="date" value="2026-09-09"');
});

test('a próxima página leva o período e nada além dele', function () {
    $ana = userWithWallet();
    depositosDiariosDeSetembro($ana, 20);
    Carbon::setTestNow('2026-09-30 12:00:00');

    $filtros = ['inicio' => '2026-09-01', 'fim' => '2026-09-20'];

    $html = $this->actingAs($ana)
        // O identificador de carteira vem junto na URL e não pode sobreviver
        // ao link, como não sobrevive à consulta.
        ->get(route('statement', $filtros + ['wallet_id' => walletOf($ana)->id]))
        ->getContent();

    expect(consultaDaProximaPagina($html))->toEqual($filtros + ['page' => '2']);
});

test('o filtro continua valendo na segunda página, sem repetir nem omitir', function () {
    $ana = userWithWallet();
    depositosDiariosDeSetembro($ana, 20);
    depositoEm('2026-10-01 10:00:00', $ana, '9,99');
    Carbon::setTestNow('2026-10-05 12:00:00');

    $filtros = ['inicio' => '2026-09-01', 'fim' => '2026-09-20'];

    $this->actingAs($ana);
    $primeira = valoresNaTela($this->get(route('statement', $filtros))->getContent());
    $segunda = valoresNaTela($this->get(route('statement', $filtros + ['page' => 2]))->getContent());

    // Quinze por página, do dia 20 para o dia 1, e o lançamento de outubro
    // fora das duas.
    expect($primeira)->toHaveCount(15)
        ->and($segunda)->toHaveCount(5)
        ->and(array_merge($primeira, $segunda))->toBe(array_map('creditoNaTela', range(20, 1)))
        ->and(array_intersect($primeira, $segunda))->toBeEmpty();
});

test('nenhum lançamento de outra carteira entra no período', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    depositoEm('2026-09-10 10:00:00', $ana, '12,34');
    depositoEm('2026-09-10 11:00:00', $bia, '99,99');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $html = $this->actingAs($ana)
        ->get(route('statement', [
            'inicio' => '2026-09-01',
            'fim' => '2026-09-30',
            'wallet_id' => walletOf($bia)->id,
        ]))
        ->getContent();

    expect(valoresNaTela($html))->toBe(['+R$ 12,34'])
        ->and($html)->not->toContain('99,99');
});

test('o período sem lançamentos diz que o vazio é do período', function () {
    $ana = userWithWallet();
    depositoEm('2026-09-10 10:00:00', $ana, '1,00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    $this->actingAs($ana)
        ->get(route('statement', ['inicio' => '2026-09-11', 'fim' => '2026-09-12']))
        ->assertOk()
        ->assertSee('Nenhuma movimentação nesse período.')
        ->assertDontSee('Nenhuma movimentação ainda.');
});

test('o filtro estreita a consulta sem abrir mão da ordem nem do índice', function () {
    $ana = userWithWallet();
    depositosDiariosDeSetembro($ana, 3);
    Carbon::setTestNow('2026-09-23 12:00:00');

    $consulta = null;
    $vinculos = [];
    DB::listen(function ($query) use (&$consulta, &$vinculos) {
        if (str_contains($query->sql, 'from "wallet_entries"') && str_contains($query->sql, 'limit')) {
            $consulta = $query->sql;
            $vinculos = $query->bindings;
        }
    });

    $this->actingAs($ana)
        ->get(route('statement', ['inicio' => '2026-09-01', 'fim' => '2026-09-02']))
        ->assertOk();

    // A carteira continua na frente das datas, que é a ordem do índice do
    // extrato, e o limite de cima é a meia-noite do dia seguinte.
    expect($consulta)->toContain('where "wallet_id" = ? and "created_at" >= ? and "created_at" < ?')
        ->and($consulta)->toContain('order by "created_at" desc, "id" desc')
        ->and((string) $vinculos[1])->toStartWith('2026-09-01 00:00:00')
        ->and((string) $vinculos[2])->toStartWith('2026-09-03 00:00:00');
});
