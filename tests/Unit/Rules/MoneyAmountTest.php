<?php

use App\Rules\FinancialRules;
use App\Rules\MoneyAmount;

/**
 * Roda a regra fora do validador e devolve a mensagem, ou `null` quando ela
 * aprovou o valor.
 */
function runMoneyAmount(mixed $value): ?string
{
    $message = null;

    (new MoneyAmount)->validate('amount', $value, function (string $failure) use (&$message) {
        $message = $failure;
    });

    return $message;
}

it('approves a value the money parser accepts', function (string $input) {
    expect(runMoneyAmount($input))->toBeNull();
})->with(['0,01', '10', '10,50', '1.000,50', '1.000.000,00']);

it('refuses a value the money parser rejects', function (mixed $input) {
    expect(runMoneyAmount($input))->toBe('Informe um valor entre R$ 0,01 e R$ 1.000.000,00, no formato 1.000,50.');
})->with(['', '0', '-10', '1000.50', '10,505', '1.000.000,01', 'abc']);

it('refuses a value that is not even text', function (mixed $input) {
    expect(runMoneyAmount($input))->not->toBeNull();
})->with([[['10,50']], [null], [10.5], [true]]);

it('offers the amount rules with the parser attached', function () {
    $rules = FinancialRules::amount();

    expect($rules)->toContain('required')
        ->and($rules)->toContain('string')
        ->and($rules[2])->toBeInstanceOf(MoneyAmount::class);
});

it('offers the idempotency key as a required uuid without a custom class', function () {
    expect(FinancialRules::idempotencyKey())->toBe(['required', 'uuid']);
});

it('names a portuguese message for every rule it composes', function () {
    expect(FinancialRules::messages())->toHaveKeys([
        'amount.required',
        'amount.string',
        'idempotency_key.required',
        'idempotency_key.uuid',
    ]);
});
