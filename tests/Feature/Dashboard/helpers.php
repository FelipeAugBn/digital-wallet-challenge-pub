<?php

// `deposit`, `transfer`, `reverseDirectly` e `valoresNaTela` já servem ao
// extrato; o painel só precisa ler o que os gráficos escrevem em texto.
require_once __DIR__.'/../Statement/helpers.php';

/**
 * O centro do anel: a porcentagem e o lado que ela representa.
 *
 * @return array{0: ?string, 1: ?string}
 */
function centroDoAnel(string $html): array
{
    preg_match('/class="anel-percentual"[^>]*>([^<]+)<.*?class="anel-lado"[^>]*>([^<]+)</s', $html, $achado);

    return [$achado[1] ?? null, $achado[2] ?? null];
}

/**
 * A legenda do anel, rótulo a valor, na ordem da tela.
 *
 * @return array<string, string>
 */
function legendaDoAnel(string $html): array
{
    preg_match_all('/<li class="[a-z]+"><span>([^<]+)<\/span><b>([^<]+)<\/b><\/li>/', $html, $achados, PREG_SET_ORDER);

    return array_combine(array_column($achados, 1), array_column($achados, 2));
}

/**
 * Os dias escritos sob as barras, na ordem da tela.
 *
 * @return list<string>
 */
function rotulosDasBarras(string $html): array
{
    preg_match_all('/class="eixo rotulo-barra"[^>]*>([^<]+)</', $html, $achados);

    return $achados[1];
}

/** A descrição em texto de um dos gráficos, pelo id do seu `<desc>`. */
function descricaoDoGrafico(string $html, string $id): ?string
{
    return preg_match('/<desc id="'.$id.'">([^<]+)<\/desc>/', $html, $achado) === 1 ? $achado[1] : null;
}
