<?php

use App\Support\Money;

test('converte os formatos brasileiros aceitos em centavos', function (string $input, int $cents) {
    expect(Money::fromInput($input)->cents())->toBe($cents);
})->with([
    ['0,01', 1],
    ['10', 1_000],
    ['10,5', 1_050],
    ['10,50', 1_050],
    ['1000,50', 100_050],
    ['1.000,50', 100_050],
    ['1.000.000,00', 100_000_000],
]);

test('ignora espaços em volta do valor', function (string $input) {
    expect(Money::fromInput($input)->cents())->toBe(1_050);
})->with([' 10,50', '10,50 ', "  10,50\t"]);

test('recusa valores que não são entrada monetária válida', function (string $input) {
    expect(Money::tryFromInput($input))->toBeNull();
})->with([
    'vazio' => '',
    'só espaços' => '   ',
    'zero' => '0',
    'zero com centavos' => '0,00',
    'negativo' => '-10',
    'negativo com centavos' => '-10,50',
    'símbolo antes' => 'R$ 10,00',
    'símbolo depois' => '10,00 R$',
    'letras' => 'abc',
    'letra junto do número' => '10a',
    'decimal americano' => '1000.50',
    'ponto como decimal' => '1.00',
    'grupo de milhar errado' => '1.0000,00',
    'separadores invertidos' => '1,000.50',
    'três casas decimais' => '10,505',
    'vírgula sem centavos' => '10,',
    'vírgula no início' => ',50',
    'ponto no início' => '.50',
    'ponto duplicado' => '1..000,50',
    'espaço no meio' => '1 000,50',
]);

test('aceita exatamente o limite da operação e recusa um centavo acima', function () {
    expect(Money::MAX_INPUT_CENTS)->toBe(100_000_000)
        ->and(Money::fromInput('1.000.000,00')->cents())->toBe(100_000_000)
        ->and(Money::tryFromInput('1.000.000,01'))->toBeNull()
        ->and(Money::tryFromInput('2.000.000,00'))->toBeNull();
});

test('recusa número grande o bastante para estourar o inteiro', function () {
    expect(Money::tryFromInput('99999999999999999999,99'))->toBeNull();
});

test('lança exceção quando a entrada chega inválida no fromInput', function () {
    Money::fromInput('1000.50');
})->throws(InvalidArgumentException::class);

test('aceita zero e valores negativos quando criado a partir de centavos', function () {
    expect(Money::fromCents(0)->cents())->toBe(0)
        ->and(Money::fromCents(-5_000)->cents())->toBe(-5_000);
});

test('formata valores na notação brasileira', function (int $cents, string $formatted) {
    expect(Money::fromCents($cents)->format())->toBe($formatted);
})->with([
    [0, 'R$ 0,00'],
    [1, 'R$ 0,01'],
    [1_000, 'R$ 10,00'],
    [100_050, 'R$ 1.000,50'],
    [100_000_000, 'R$ 1.000.000,00'],
    [-5_000, '-R$ 50,00'],
    [-1, '-R$ 0,01'],
    [PHP_INT_MAX, 'R$ 92.233.720.368.547.758,07'],
    [PHP_INT_MIN, '-R$ 92.233.720.368.547.758,08'],
]);

test('soma e subtrai valores', function () {
    expect(Money::fromCents(1_000)->plus(Money::fromCents(500))->cents())->toBe(1_500)
        ->and(Money::fromCents(1_000)->minus(Money::fromCents(400))->cents())->toBe(600);
});

test('permite que uma subtração termine negativa, como num estorno', function () {
    $result = Money::fromCents(3_000)->minus(Money::fromCents(8_000));

    expect($result->cents())->toBe(-5_000)
        ->and($result->format())->toBe('-R$ 50,00');
});

test('nunca altera as instâncias usadas numa operação', function () {
    $balance = Money::fromCents(1_000);
    $deposit = Money::fromCents(500);

    $result = $balance->plus($deposit);

    expect($balance->cents())->toBe(1_000)
        ->and($deposit->cents())->toBe(500)
        ->and($result)->not->toBe($balance);
});

test('mantém a propriedade de centavos somente leitura e a classe final', function () {
    $reflection = new ReflectionClass(Money::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->getProperty('cents')->isReadOnly())->toBeTrue()
        ->and($reflection->getConstructor()->isPrivate())->toBeTrue();
});

test('expõe os centavos como inteiro de verdade', function () {
    expect(Money::fromInput('1.000,50')->cents())->toBeInt();
});

test('não tem aritmética de ponto flutuante no código-fonte, comentários à parte', function () {
    $path = dirname(__DIR__, 3).'/app/Support/Money.php';
    $code = '';

    foreach (token_get_all(file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    expect($code)->not->toContain('float')
        ->and($code)->not->toContain('double')
        ->and($code)->not->toContain('number_format')
        ->and($code)->not->toContain('round(');
});
