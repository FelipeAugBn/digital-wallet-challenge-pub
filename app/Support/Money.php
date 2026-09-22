<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Um valor em dinheiro guardado como centavos inteiros.
 *
 * A classe e imutavel: toda conta devolve uma instancia nova. Nenhuma etapa usa
 * ponto flutuante, porque a aproximacao binaria de decimais acumula diferenca
 * onde o dinheiro precisa fechar exato.
 */
final class Money
{
    /** O teto de uma operacao: R$ 1.000.000,00 em centavos. */
    public const MAX_INPUT_CENTS = 100_000_000;

    /**
     * Aceita o formato brasileiro com ou sem separador de milhar e com no
     * maximo duas casas decimais. O formato americano `1000.50` nao casa
     * porque o grupo apos o ponto exige exatamente tres digitos.
     */
    private const INPUT_PATTERN = '/^(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?$/';

    /** O construtor e privado para que so os metodos nomeados criem valores. */
    private function __construct(private readonly int $cents) {}

    /**
     * Cria a partir de centavos ja confiaveis, vindos do banco ou de uma conta.
     *
     * Aceita zero e negativo de proposito: saldo pode ser zero e o estorno pode
     * deixar a carteira negativa. O limite vale so para a entrada do usuario.
     */
    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Converte o que a pessoa digitou, ou devolve `null` quando o valor nao
     * serve. Usado pela validacao, que precisa da recusa sem excecao.
     */
    public static function tryFromInput(string $value): ?self
    {
        if (preg_match(self::INPUT_PATTERN, trim($value), $matches) !== 1) {
            return null;
        }

        // `ltrim` remove zeros a esquerda; o corte por tamanho barra numeros
        // gigantes antes da multiplicacao, que estouraria o inteiro.
        $units = ltrim(str_replace('.', '', $matches[1]), '0');

        if (strlen($units) > 9) {
            return null;
        }

        $decimals = str_pad($matches[2] ?? '', 2, '0');
        $cents = ((int) $units) * 100 + (int) $decimals;

        if ($cents === 0 || $cents > self::MAX_INPUT_CENTS) {
            return null;
        }

        return new self($cents);
    }

    /**
     * Mesma conversao, para quando o valor ja passou pela validacao e um
     * formato invalido significa defeito de programacao, nao erro da pessoa.
     */
    public static function fromInput(string $value): self
    {
        return self::tryFromInput($value) ?? throw new InvalidArgumentException('Valor monetario invalido.');
    }

    /** Os centavos, que e o que vai para o banco. */
    public function cents(): int
    {
        return $this->cents;
    }

    /** Soma sem tocar nas duas instancias originais. */
    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /** Subtrai; o resultado pode ser negativo, como acontece no estorno. */
    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /**
     * Escreve no formato brasileiro, com o sinal antes do simbolo: `-R$ 50,00`.
     *
     * Tudo acontece sobre os digitos em texto: o sinal e separado na string e
     * os centavos sao os dois ultimos digitos. Assim nao existe conta que possa
     * virar ponto flutuante, e o menor inteiro possivel tambem e formatado.
     */
    public function format(): string
    {
        $digits = (string) $this->cents;
        $sign = '';

        if (str_starts_with($digits, '-')) {
            $sign = '-';
            $digits = substr($digits, 1);
        }

        // Tres digitos no minimo, para que sobre pelo menos um real antes da virgula.
        $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);
        $units = substr($digits, 0, -2);
        $decimals = substr($digits, -2);
        $grouped = strrev(implode('.', str_split(strrev($units), 3)));

        return $sign.'R$ '.$grouped.','.$decimals;
    }
}
