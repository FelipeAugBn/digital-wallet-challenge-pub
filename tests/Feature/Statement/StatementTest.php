<?php

use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

test('exige autenticação', function () {
    $this->get(route('statement'))->assertRedirect(route('login'));
});

test('mostra somente lançamentos da própria carteira', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    deposit($bia, '77,00');
    transfer($ana, $bia, '30,00');

    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    expect(valoresNaTela($html))->toBe(['-R$ 30,00'])
        ->and($html)->not->toContain('R$ 77,00');
});

test('mostra no máximo 15 lançamentos por página', function () {
    $ana = userWithWallet();
    depositosCrescentes($ana, 20);

    expect(valoresNaTela($this->actingAs($ana)->get(route('statement'))->getContent()))->toHaveCount(15)
        ->and(valoresNaTela($this->get(route('statement', ['page' => 2]))->getContent()))->toHaveCount(5);
});

test('pagina sem repetir nem omitir lançamento', function () {
    $ana = userWithWallet();
    depositosCrescentes($ana, 20);

    $this->actingAs($ana);
    $primeira = valoresNaTela($this->get(route('statement'))->getContent());
    $segunda = valoresNaTela($this->get(route('statement', ['page' => 2]))->getContent());

    $tudo = array_merge($primeira, $segunda);
    $esperado = array_map('creditoNaTela', range(20, 1));

    expect($tudo)->toBe($esperado)
        ->and(array_unique($tudo))->toHaveCount(20)
        ->and(array_intersect($primeira, $segunda))->toBeEmpty();
});

test('mantém o isolamento também na segunda página', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    // Faixas de valor separadas: qualquer centavo da Bia que apareça na tela da
    // Ana é vazamento, e a Bia depositou depois, então os lançamentos dela têm
    // id maior e seriam os primeiros a escapar se faltasse o filtro.
    depositosCrescentes($ana, 20, 1);
    depositosCrescentes($bia, 20, 101);

    $daAna = valoresNaTela($this->actingAs($ana)->get(route('statement', ['page' => 2]))->getContent());

    expect($daAna)->toBe(array_map('creditoNaTela', [5, 4, 3, 2, 1]));

    foreach (range(101, 120) as $centavos) {
        expect($daAna)->not->toContain(creditoNaTela($centavos));
    }
});

test('ordena do mais recente para o mais antigo', function () {
    $ana = userWithWallet();
    depositosCrescentes($ana, 3);

    expect(valoresNaTela($this->actingAs($ana)->get(route('statement'))->getContent()))
        ->toBe(['+R$ 0,03', '+R$ 0,02', '+R$ 0,01']);
});

test('desempata por id quando os lançamentos têm o mesmo instante', function () {
    $ana = userWithWallet();
    depositosCrescentes($ana, 6);

    // Todos no mesmo segundo: sem o desempate por id a ordem ficaria a critério
    // do banco, e a paginação deixaria de ser confiável.
    mesmoInstante();

    $ordem = valoresNaTela($this->actingAs($ana)->get(route('statement'))->getContent());

    expect($ordem)->toBe(['+R$ 0,06', '+R$ 0,05', '+R$ 0,04', '+R$ 0,03', '+R$ 0,02', '+R$ 0,01']);
});

test('ordena pela cláusula que a SPEC exige, sem depender do índice', function () {
    $ana = userWithWallet();
    deposit($ana, '1,00');

    $consulta = null;
    DB::listen(function ($query) use (&$consulta) {
        if (str_contains($query->sql, 'from "wallet_entries"') && str_contains($query->sql, 'limit')) {
            $consulta = $query->sql;
        }
    });

    $this->actingAs($ana)->get(route('statement'))->assertOk();

    // O índice do extrato já guarda as linhas em `created_at desc, id desc`,
    // então tirar o desempate da consulta não mudaria nada enquanto ele
    // existisse — e passaria despercebido no dia em que ele mudasse. Quem
    // garante a ordem é a consulta, e a SPEC a define com essas duas colunas.
    expect($consulta)->toContain('order by "created_at" desc, "id" desc');
});

test('avisa quando ainda não há movimentação', function () {
    $this->actingAs(userWithWallet())->get(route('statement'))
        ->assertOk()
        ->assertSee('Nenhuma movimentação ainda.');
});

test('não aceita carteira vinda da URL', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    deposit($bia, '77,00');

    $html = $this->actingAs($ana)
        ->get(route('statement', ['wallet_id' => walletOf($bia)->id, 'wallet' => walletOf($bia)->id]))
        ->getContent();

    expect($html)->not->toContain('R$ 77,00')
        ->and(valoresNaTela($html))->toBeEmpty();
});

test('abrir o extrato não movimenta dinheiro nem trava carteira', function () {
    $ana = userWithWallet(5_000);
    deposit($ana, '10,00');

    $consultas = [];
    DB::listen(function ($query) use (&$consultas) {
        $consultas[] = $query->sql;
    });

    $this->actingAs($ana)->get(route('statement'))->assertOk();

    $escritas = array_filter($consultas, fn (string $sql) => (bool) preg_match('/^(insert|update|delete)/i', $sql));

    expect(array_filter($consultas, fn (string $sql) => str_contains($sql, 'for update')))->toBeEmpty()
        ->and($escritas)->toBeEmpty()
        ->and(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(6_000);
});

test('não dispara uma consulta por lançamento', function () {
    $ana = userWithWallet(500_000);
    $bia = userWithWallet();
    $carol = userWithWallet();

    foreach (range(1, 6) as $i) {
        transfer($ana, $i % 2 === 0 ? $bia : $carol, '1,00');
    }

    $consultas = [];
    DB::listen(function ($query) use (&$consultas) {
        $consultas[] = $query->sql;
    });

    $this->actingAs($ana)->get(route('statement'))->assertOk();

    // Seis lançamentos de três pessoas diferentes: com N+1 o número de
    // consultas cresceria com a lista, e aqui ele não pode depender dela.
    expect(count($consultas))->toBeLessThan(12);
});
