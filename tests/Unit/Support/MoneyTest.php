<?php

use App\Support\Money;

it('converts the accepted brazilian formats to cents', function (string $input, int $cents) {
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

it('ignores spaces around the value', function (string $input) {
    expect(Money::fromInput($input)->cents())->toBe(1_050);
})->with([' 10,50', '10,50 ', "  10,50\t"]);

it('rejects values that are not valid money input', function (string $input) {
    expect(Money::tryFromInput($input))->toBeNull();
})->with([
    'vazio' => '',
    'so espacos' => '   ',
    'zero' => '0',
    'zero com centavos' => '0,00',
    'negativo' => '-10',
    'negativo com centavos' => '-10,50',
    'simbolo antes' => 'R$ 10,00',
    'simbolo depois' => '10,00 R$',
    'letras' => 'abc',
    'letra junto do numero' => '10a',
    'decimal americano' => '1000.50',
    'ponto como decimal' => '1.00',
    'grupo de milhar errado' => '1.0000,00',
    'separadores invertidos' => '1,000.50',
    'tres casas decimais' => '10,505',
    'virgula sem centavos' => '10,',
    'virgula no inicio' => ',50',
    'ponto no inicio' => '.50',
    'ponto duplicado' => '1..000,50',
    'espaco no meio' => '1 000,50',
]);

it('accepts exactly the operation limit and refuses one cent above it', function () {
    expect(Money::MAX_INPUT_CENTS)->toBe(100_000_000)
        ->and(Money::fromInput('1.000.000,00')->cents())->toBe(100_000_000)
        ->and(Money::tryFromInput('1.000.000,01'))->toBeNull()
        ->and(Money::tryFromInput('2.000.000,00'))->toBeNull();
});

it('refuses a number long enough to overflow the integer', function () {
    expect(Money::tryFromInput('99999999999999999999,99'))->toBeNull();
});

it('throws when the input reaches fromInput already invalid', function () {
    Money::fromInput('1000.50');
})->throws(InvalidArgumentException::class);

it('accepts zero and negative amounts when built from cents', function () {
    expect(Money::fromCents(0)->cents())->toBe(0)
        ->and(Money::fromCents(-5_000)->cents())->toBe(-5_000);
});

it('formats amounts in the brazilian notation', function (int $cents, string $formatted) {
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

it('adds and subtracts amounts', function () {
    expect(Money::fromCents(1_000)->plus(Money::fromCents(500))->cents())->toBe(1_500)
        ->and(Money::fromCents(1_000)->minus(Money::fromCents(400))->cents())->toBe(600);
});

it('lets a subtraction end up negative, as a reversal does', function () {
    $result = Money::fromCents(3_000)->minus(Money::fromCents(8_000));

    expect($result->cents())->toBe(-5_000)
        ->and($result->format())->toBe('-R$ 50,00');
});

it('never changes the instances used in an operation', function () {
    $balance = Money::fromCents(1_000);
    $deposit = Money::fromCents(500);

    $result = $balance->plus($deposit);

    expect($balance->cents())->toBe(1_000)
        ->and($deposit->cents())->toBe(500)
        ->and($result)->not->toBe($balance);
});

it('keeps the cents property readonly and the class final', function () {
    $reflection = new ReflectionClass(Money::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->getProperty('cents')->isReadOnly())->toBeTrue()
        ->and($reflection->getConstructor()->isPrivate())->toBeTrue();
});

it('exposes the cents as a real integer', function () {
    expect(Money::fromInput('1.000,50')->cents())->toBeInt();
});

it('has no floating point arithmetic in its source, comments apart', function () {
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
