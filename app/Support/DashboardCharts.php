<?php

namespace App\Support;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\WalletEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Os tres graficos do painel, reduzidos a numeros e caminhos SVG.
 *
 * O desenho acontece na Blade; aqui ficam as contas. Tudo sai dos lancamentos
 * de uma janela de tempo: o saldo depois de cada dia, quanto entrou e saiu em
 * cada dia e a composicao do mes corrente. Nenhum numero e recalculado a
 * partir de outro grafico, e nada aqui escreve no banco.
 */
final class DashboardCharts
{
    /** Quantos dias para tras a janela alcanca: cinco semanas. */
    public const DIAS = 35;

    /** A caixa da curva do saldo, em unidades do SVG. */
    public const CURVA_LARGURA = 760;

    public const CURVA_ALTURA = 220;

    /** A caixa das barras; a altura depende de quanto saiu. */
    public const BARRAS_LARGURA = 320;

    /** A linha do zero das barras: entradas sobem dela, saidas descem. */
    public const BARRAS_MEIO = 150;

    /** O raio do anel do mes. */
    public const ANEL_RAIO = 74;

    /** A margem esquerda guarda os rotulos do eixo; a direita, o saldo de hoje. */
    private const CURVA_MARGENS = ['esquerda' => 64, 'direita' => 110, 'topo' => 18, 'base' => 34];

    private const BARRAS = ['meio' => self::BARRAS_MEIO, 'altura' => 128, 'folga' => 4, 'margem' => 12];

    /**
     * @param  list<array{x: float, y: float, titulo: string, estorno: bool}>  $pontos  um ponto por dia com movimento
     * @param  array{x: float, y: float}  $fim  o saldo de hoje
     * @param  list<array{x: float, rotulo: string, ancora: string}>  $eixo  os rotulos de data da curva
     * @param  list<array{x1: float, x2: float, y1: float, y2: float, forte: bool}>  $grade  o papel milimetrado: um traco por dia e um por degrau de valor
     * @param  list<array{x: float, y: float, rotulo: string}>  $eixoY  os rotulos de valor da curva
     * @param  list<array{x: float, rotulo: ?string, entrou: string, saiu: string, alturaEntrou: float, alturaSaiu: float, alturaEntrouEstorno: float, alturaSaiuEstorno: float}>  $barras
     * @param  list<array{rotulo: string, valor: string, classe: string}>  $legendaAnel
     * @param  array{valor: string, classe: string}  $seteDias  o que mudou no saldo nos ultimos sete dias
     * @param  array{valor: string, classe: string}  $liquidoMes  o que mudou no saldo desde o inicio do mes
     */
    private function __construct(
        public readonly string $linha,
        public readonly string $area,
        public readonly array $pontos,
        public readonly array $fim,
        public readonly array $eixo,
        public readonly array $grade,
        public readonly array $eixoY,
        public readonly string $saldoAtual,
        public readonly string $descricaoCurva,
        public readonly bool $temEstorno,
        public readonly array $barras,
        public readonly int $barrasAltura,
        public readonly string $descricaoBarras,
        public readonly string $mes,
        public readonly int $percentual,
        public readonly string $lado,
        public readonly bool $mesVazio,
        public readonly float $arcoEntrou,
        public readonly float $arcoSaiu,
        public readonly float $deslocamentoSaiu,
        public readonly array $legendaAnel,
        public readonly string $descricaoAnel,
        public readonly array $seteDias,
        public readonly array $liquidoMes,
    ) {}

    /**
     * Monta os graficos a partir dos lancamentos da janela, em ordem cronologica.
     *
     * A janela chega inteira: nenhum total aqui e parcial. `$saldoAtual` vem
     * da carteira, que e o valor oficial, e a curva termina nele. O saldo antes
     * da janela sai do primeiro lancamento dela, que guarda o saldo depois
     * dele: basta desfazer o proprio valor.
     *
     * @param  Collection<int, WalletEntry>  $lancamentos
     */
    public static function from(Collection $lancamentos, int $saldoAtual, Carbon $inicio, Carbon $hoje): self
    {
        $primeiro = $lancamentos->first();
        $saldoInicial = $primeiro->balance_after - self::sinalizado($primeiro);

        $dias = self::porDia($lancamentos);
        $temEstorno = collect($dias)->contains(fn (array $dia) => $dia['estorno']);

        [$linha, $area, $pontos, $fim, $eixo, $grade, $eixoY] = self::curva($dias, $saldoInicial, $saldoAtual, $inicio, $hoje);
        [$barras, $barrasAltura] = self::barras($dias);
        $anel = self::anel($lancamentos, $hoje);

        $descricaoCurva = sprintf(
            'O saldo era %s em %s e termina em %s hoje.',
            Money::fromCents($saldoInicial)->format(),
            self::porExtenso($inicio),
            Money::fromCents($saldoAtual)->format(),
        );

        // A leitura de sete dias e a soma com sinal do que aconteceu na semana:
        // um numero so, ao lado do saldo, para dizer se ela foi de entrada ou de saida.
        $seteDias = (int) $lancamentos
            ->filter(fn (WalletEntry $l) => $l->created_at->greaterThanOrEqualTo($hoje->copy()->subDays(7)))
            ->sum(fn (WalletEntry $l) => self::sinalizado($l));

        $descricaoBarras = implode('; ', array_map(
            fn (array $dia) => sprintf(
                '%s: entrou %s%s, saiu %s%s',
                self::curto($dia['data']),
                Money::fromCents($dia['entrou'])->format(),
                self::parteDeEstorno($dia['entrouEstorno']),
                Money::fromCents($dia['saiu'])->format(),
                self::parteDeEstorno($dia['saiuEstorno']),
            ),
            $dias,
        )).'.';

        return new self(...[
            'linha' => $linha,
            'area' => $area,
            'pontos' => $pontos,
            'fim' => $fim,
            'eixo' => $eixo,
            'grade' => $grade,
            'eixoY' => $eixoY,
            'saldoAtual' => Money::fromCents($saldoAtual)->format(),
            'descricaoCurva' => $descricaoCurva,
            'temEstorno' => $temEstorno,
            'barras' => $barras,
            'barrasAltura' => $barrasAltura,
            'descricaoBarras' => $descricaoBarras,
            'mes' => ucfirst(StatementEntry::MESES[$hoje->month]),
            'seteDias' => self::leitura($seteDias),
            ...$anel,
        ]);
    }

    /**
     * Um valor com sinal e a classe que diz a direcao, para as leituras ao
     * lado do saldo.
     *
     * @return array{valor: string, classe: string}
     */
    /** O valor com o sinal na frente: `+R$ 10,00`, `-R$ 10,00` ou `R$ 0,00`. */
    private static function comSinal(int $centavos): string
    {
        return ($centavos > 0 ? '+' : '').Money::fromCents($centavos)->format();
    }

    private static function leitura(int $centavos): array
    {
        return [
            'valor' => self::comSinal($centavos),
            'classe' => $centavos > 0 ? 'positivo' : ($centavos < 0 ? 'negativo' : 'neutro'),
        ];
    }

    /**
     * Agrupa por dia: quanto entrou, quanto saiu, quanto de cada lado esta
     * ligado a estorno, se o dia tem estorno e o saldo no fim dele.
     *
     * `entrouEstorno` e `saiuEstorno` sao partes de `entrou` e `saiu`, nunca
     * parcelas a somar: e o quanto daquele lado veio de uma reversao ou de uma
     * operacao que acabou desfeita.
     *
     * @param  Collection<int, WalletEntry>  $lancamentos
     * @return list<array{data: Carbon, ultimo: Carbon, entrou: int, saiu: int, entrouEstorno: int, saiuEstorno: int, estorno: bool, saldo: int}>
     */
    private static function porDia(Collection $lancamentos): array
    {
        $dias = [];

        foreach ($lancamentos as $lancamento) {
            $chave = $lancamento->created_at->format('Y-m-d');
            $dia = $dias[$chave] ?? [
                'data' => $lancamento->created_at->copy()->startOfDay(),
                'ultimo' => $lancamento->created_at,
                'entrou' => 0,
                'saiu' => 0,
                'entrouEstorno' => 0,
                'saiuEstorno' => 0,
                'estorno' => false,
                'saldo' => 0,
            ];

            // Cada lado soma o total do dia; a parte ligada a estorno fica
            // separada, para a barra mostrar quanto do total foi isso.
            $lado = $lancamento->type === WalletEntryType::Credit ? 'entrou' : 'saiu';
            $dia[$lado] += $lancamento->amount;

            if (self::ligadoAEstorno($lancamento)) {
                $dia[$lado.'Estorno'] += $lancamento->amount;
                $dia['estorno'] = true;
            }
            $dia['ultimo'] = $lancamento->created_at;
            $dia['saldo'] = $lancamento->balance_after;

            $dias[$chave] = $dia;
        }

        return array_values($dias);
    }

    /**
     * A curva: do saldo antes da janela ao saldo de hoje, passando pelo saldo
     * no fim de cada dia com movimento. O eixo vertical comeca no zero, ou
     * abaixo dele quando a carteira ficou negativa, e fecha num degrau
     * redondo acima do maior saldo, para que a grade termine numa linha com
     * rotulo.
     *
     * @param  list<array{data: Carbon, ultimo: Carbon, entrou: int, saiu: int, entrouEstorno: int, saiuEstorno: int, estorno: bool, saldo: int}>  $dias
     * @return array{0: string, 1: string, 2: list<array<string, mixed>>, 3: array{x: float, y: float}, 4: list<array<string, mixed>>, 5: list<array<string, mixed>>, 6: list<array<string, mixed>>}
     */
    private static function curva(array $dias, int $saldoInicial, int $saldoAtual, Carbon $inicio, Carbon $hoje): array
    {
        $m = self::CURVA_MARGENS;
        $largura = self::CURVA_LARGURA - $m['esquerda'] - $m['direita'];
        $altura = self::CURVA_ALTURA - $m['topo'] - $m['base'];
        $duracao = max($hoje->getTimestamp() - $inicio->getTimestamp(), 1);

        $saldos = array_merge([$saldoInicial, $saldoAtual], array_column($dias, 'saldo'));
        $degrau = self::degrau(max(...$saldos) - min(0, ...$saldos));
        $minimo = (int) (floor(min(0, ...$saldos) / $degrau) * $degrau);
        $maximo = (int) (ceil(max(...$saldos) / $degrau) * $degrau);
        if ($maximo === $minimo) {
            $maximo = $minimo + $degrau;
        }

        $x = fn (Carbon $t) => $m['esquerda'] + ($t->getTimestamp() - $inicio->getTimestamp()) / $duracao * $largura;
        $y = fn (int $c) => $m['topo'] + (1 - ($c - $minimo) / ($maximo - $minimo)) * $altura;

        // O papel milimetrado: um traco por dia, mais forte a cada semana, e um
        // traco com rotulo por degrau de valor.
        $grade = [];
        $eixoY = [];
        for ($dia = 0; $dia <= self::DIAS; $dia++) {
            $xx = round($x($inicio->copy()->addDays($dia)), 1);
            $grade[] = ['x1' => $xx, 'x2' => $xx, 'y1' => (float) $m['topo'], 'y2' => round($m['topo'] + $altura, 1), 'forte' => $dia % 7 === 0];
        }
        for ($valor = $minimo; $valor <= $maximo; $valor += $degrau) {
            $yy = round($y($valor), 1);
            $grade[] = ['x1' => (float) $m['esquerda'], 'x2' => round($m['esquerda'] + $largura, 1), 'y1' => $yy, 'y2' => $yy, 'forte' => $valor === $minimo || $valor === $maximo];
            $eixoY[] = ['x' => $m['esquerda'] - 10, 'y' => round($yy + 4, 1), 'rotulo' => self::rotuloDeValor($valor, $degrau)];
        }

        $serie = [[$x($inicio), $y($saldoInicial)]];
        $pontos = [];

        foreach ($dias as $dia) {
            $serie[] = [$x($dia['ultimo']), $y($dia['saldo'])];
            $pontos[] = [
                'x' => round($x($dia['ultimo']), 1),
                'y' => round($y($dia['saldo']), 1),
                'titulo' => self::curto($dia['data']).': saldo '.Money::fromCents($dia['saldo'])->format(),
                'estorno' => $dia['estorno'],
            ];
        }

        $serie[] = [$x($hoje), $y($saldoAtual)];
        $fim = ['x' => round($x($hoje), 1), 'y' => round($y($saldoAtual), 1)];

        $linha = self::suave($serie);
        $base = round($y($minimo), 1);
        $area = sprintf('%s L%s,%s L%s,%s Z', $linha, $fim['x'], $base, round($serie[0][0], 1), $base);

        // Uma data por semana, e "hoje" na ponta.
        $eixo = [];
        for ($dia = 0; $dia < self::DIAS; $dia += 7) {
            $data = $inicio->copy()->addDays($dia);
            $eixo[] = ['x' => round($x($data), 1), 'rotulo' => self::curto($data), 'ancora' => $dia === 0 ? 'start' : 'middle'];
        }
        $eixo[] = ['x' => $fim['x'], 'rotulo' => 'hoje', 'ancora' => 'end'];

        return [$linha, $area, $pontos, $fim, $eixo, $grade, $eixoY];
    }

    /**
     * O rotulo de um degrau da grade, sem o "R$": em reais inteiros quando o
     * degrau e redondo em reais, com centavos so quando a carteira e pequena
     * o bastante para os centavos importarem.
     */
    private static function rotuloDeValor(int $centavos, int $degrau): string
    {
        $texto = Money::fromCents($centavos)->format();
        $semMoeda = str_replace('R$ ', '', $texto);

        return $degrau % 100 === 0 ? substr($semMoeda, 0, -3) : $semMoeda;
    }

    /**
     * O degrau redondo da grade de valores: 1, 2, 2,5 ou 5 vezes uma potencia
     * de dez, em centavos, o menor que divide a faixa em ate quatro partes.
     */
    private static function degrau(int $faixa): int
    {
        $bruto = max($faixa, 4) / 4;
        $potencia = 10 ** floor(log10($bruto));

        foreach ([1, 2, 2.5, 5, 10] as $multiplo) {
            if ($multiplo * $potencia >= $bruto) {
                return max(1, (int) round($multiplo * $potencia));
            }
        }

        return max(1, (int) round(10 * $potencia));
    }

    /**
     * As barras: uma dupla por dia com movimento, entradas para cima e saidas
     * para baixo, na mesma escala. A altura da caixa acompanha a maior saida,
     * para nao sobrar vazio embaixo.
     *
     * @param  list<array{data: Carbon, ultimo: Carbon, entrou: int, saiu: int, entrouEstorno: int, saiuEstorno: int, estorno: bool, saldo: int}>  $dias
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private static function barras(array $dias): array
    {
        $b = self::BARRAS;
        $quantidade = count($dias);
        $coluna = (self::BARRAS_LARGURA - 2 * $b['margem']) / $quantidade;
        $maior = max(1, ...array_column($dias, 'entrou'), ...array_column($dias, 'saiu'));
        $passo = (int) ceil($quantidade / 6);
        $escala = fn (int $valor) => $valor === 0 ? 0.0 : max(3.0, $valor / $maior * $b['altura']);

        $barras = [];
        foreach ($dias as $i => $dia) {
            // A parte de estorno e um trecho da propria barra, na mesma escala,
            // e nunca maior que ela: no limite, a barra inteira e estorno.
            $alturaEntrou = round($escala($dia['entrou']), 1);
            $alturaSaiu = round($escala($dia['saiu']), 1);

            $barras[] = [
                'x' => round($b['margem'] + $coluna * $i + $coluna / 2, 1),
                'rotulo' => $i % $passo === 0 ? self::curto($dia['data']) : null,
                'entrou' => Money::fromCents($dia['entrou'])->format().self::parteDeEstorno($dia['entrouEstorno']),
                'saiu' => Money::fromCents($dia['saiu'])->format().self::parteDeEstorno($dia['saiuEstorno']),
                'alturaEntrou' => $alturaEntrou,
                'alturaSaiu' => $alturaSaiu,
                'alturaEntrouEstorno' => $dia['entrou'] === 0 ? 0.0 : round(min($alturaEntrou, $dia['entrouEstorno'] / $dia['entrou'] * $alturaEntrou), 1),
                'alturaSaiuEstorno' => $dia['saiu'] === 0 ? 0.0 : round(min($alturaSaiu, $dia['saiuEstorno'] / $dia['saiu'] * $alturaSaiu), 1),
            ];
        }

        $maiorSaida = max(array_column($barras, 'alturaSaiu'));
        $alturaCaixa = (int) ceil($b['meio'] + $b['folga'] + $maiorSaida + 24);

        return [$barras, $alturaCaixa];
    }

    /**
     * O anel do mes corrente: a fatia do movimento que entrou contra a que
     * saiu. No centro vai a porcentagem do lado maior, que cabe em qualquer
     * carteira; os valores, que crescem, ficam na legenda.
     *
     * A legenda lista as acoes do mes, cada uma com o sinal que teve na
     * carteira, e so as que aconteceram: depositado, recebido, enviado e
     * estornado somam o liquido. "Estornado" conta os lancamentos de reversao
     * do mes, que e quando o dinheiro voltou ou saiu; contar pelo estado da
     * operacao original poria o valor no mes dela, e nao no mes do estorno.
     *
     * @param  Collection<int, WalletEntry>  $lancamentos
     * @return array<string, mixed>
     */
    private static function anel(Collection $lancamentos, Carbon $hoje): array
    {
        $doMes = $lancamentos->filter(fn (WalletEntry $l) => $l->created_at->greaterThanOrEqualTo($hoje->copy()->startOfMonth()));

        $entrou = (int) $doMes->filter(fn (WalletEntry $l) => $l->type === WalletEntryType::Credit)->sum('amount');
        $saiu = (int) $doMes->filter(fn (WalletEntry $l) => $l->type === WalletEntryType::Debit)->sum('amount');
        $liquido = $entrou - $saiu;

        // Cada acao com o sinal que teve nesta carteira: o estorno de um envio
        // devolve, o de um deposito ou de um recebimento tira.
        $porAcao = fn (TransactionType $tipo, ?WalletEntryType $lado = null) => (int) $doMes
            ->filter(fn (WalletEntry $l) => $l->transaction->type === $tipo && ($lado === null || $l->type === $lado))
            ->sum(fn (WalletEntry $l) => $l->type === WalletEntryType::Credit ? $l->amount : -$l->amount);
        $acoes = [
            ['rotulo' => 'Depositado', 'valor' => $porAcao(TransactionType::Deposit), 'classe' => 'depositado'],
            ['rotulo' => 'Recebido', 'valor' => $porAcao(TransactionType::Transfer, WalletEntryType::Credit), 'classe' => 'recebido'],
            ['rotulo' => 'Enviado', 'valor' => $porAcao(TransactionType::Transfer, WalletEntryType::Debit), 'classe' => 'enviado'],
            ['rotulo' => 'Estornado', 'valor' => $porAcao(TransactionType::Reversal), 'classe' => 'estornado'],
        ];
        $legenda = array_values(array_map(
            fn (array $acao) => [...$acao, 'valor' => self::comSinal($acao['valor'])],
            array_filter($acoes, fn (array $acao) => $acao['valor'] !== 0),
        ));
        $legenda[] = ['rotulo' => 'Líquido', 'valor' => self::comSinal($liquido), 'classe' => 'liquido'];
        $total = $entrou + $saiu;

        $fracao = $total === 0 ? 0.0 : $entrou / $total;
        $circunferencia = 2 * M_PI * self::ANEL_RAIO;
        $folga = ($entrou > 0 && $saiu > 0) ? 3 : 0;

        return [
            'percentual' => (int) round(max($fracao, 1 - $fracao) * 100),
            'lado' => $fracao >= 0.5 ? 'entrou' : 'saiu',
            'mesVazio' => $total === 0,
            'arcoEntrou' => round(max($circunferencia * $fracao - 2 * $folga, 0), 2),
            'arcoSaiu' => round(max($circunferencia * (1 - $fracao) - 2 * $folga, 0), 2),
            'deslocamentoSaiu' => round(-($circunferencia * $fracao + $folga), 2),
            'liquidoMes' => self::leitura($liquido),
            'legendaAnel' => $legenda,
            'descricaoAnel' => sprintf(
                'Entrou %s e saiu %s: %d%% do movimento do mês %s. Líquido %s.',
                Money::fromCents($entrou)->format(),
                Money::fromCents($saiu)->format(),
                (int) round(max($fracao, 1 - $fracao) * 100),
                $fracao >= 0.5 ? 'entrou' : 'saiu',
                Money::fromCents($liquido)->format(),
            ),
        ];
    }

    /**
     * Uma curva que passa por todos os pontos, sem biblioteca.
     *
     * Catmull-Rom convertida em Bezier cubica: cada trecho olha o ponto
     * anterior e o seguinte para escolher a tangente, e as pontas repetem o
     * proprio ponto.
     *
     * @param  list<array{0: float, 1: float}>  $pontos
     */
    private static function suave(array $pontos): string
    {
        $ultimo = count($pontos) - 1;
        $caminho = sprintf('M%s,%s', round($pontos[0][0], 1), round($pontos[0][1], 1));

        for ($i = 0; $i < $ultimo; $i++) {
            $p0 = $pontos[max($i - 1, 0)];
            $p1 = $pontos[$i];
            $p2 = $pontos[$i + 1];
            $p3 = $pontos[min($i + 2, $ultimo)];

            $caminho .= sprintf(
                ' C%s,%s %s,%s %s,%s',
                round($p1[0] + ($p2[0] - $p0[0]) / 6, 1),
                round($p1[1] + ($p2[1] - $p0[1]) / 6, 1),
                round($p2[0] - ($p3[0] - $p1[0]) / 6, 1),
                round($p2[1] - ($p3[1] - $p1[1]) / 6, 1),
                round($p2[0], 1),
                round($p2[1], 1),
            );
        }

        return $caminho;
    }

    /** O valor com sinal: credito soma, debito subtrai. */
    private static function sinalizado(WalletEntry $lancamento): int
    {
        return $lancamento->type === WalletEntryType::Credit ? $lancamento->amount : -$lancamento->amount;
    }

    /** O lancamento e um estorno, ou pertence a uma operacao que foi estornada. */
    private static function ligadoAEstorno(WalletEntry $lancamento): bool
    {
        return $lancamento->transaction->type === TransactionType::Reversal
            || $lancamento->transaction->status === TransactionStatus::Reversed;
    }

    /** ` (R$ 10,00 de estorno)`, ou nada quando o valor nao tem parte de estorno. */
    private static function parteDeEstorno(int $centavos): string
    {
        return $centavos === 0 ? '' : ' ('.Money::fromCents($centavos)->format().' de estorno)';
    }

    /** `22 ago`: o dia e as tres primeiras letras do mes. */
    private static function curto(Carbon $data): string
    {
        return $data->day.' '.mb_substr(StatementEntry::MESES[$data->month], 0, 3);
    }

    /** `22 de agosto`, como a pessoa diria. */
    private static function porExtenso(Carbon $data): string
    {
        return $data->day.' de '.StatementEntry::MESES[$data->month];
    }
}
