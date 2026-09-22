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

test('aprova valor que o conversor de dinheiro aceita', function (string $input) {
    expect(runMoneyAmount($input))->toBeNull();
})->with(['0,01', '10', '10,50', '1.000,50', '1.000.000,00']);

test('recusa valor que o conversor de dinheiro rejeita', function (mixed $input) {
    expect(runMoneyAmount($input))->toBe('Informe um valor entre R$ 0,01 e R$ 1.000.000,00, no formato 1.000,50.');
})->with(['', '0', '-10', '1000.50', '10,505', '1.000.000,01', 'abc']);

test('recusa valor que nem texto é', function (mixed $input) {
    expect(runMoneyAmount($input))->not->toBeNull();
})->with([[['10,50']], [null], [10.5], [true]]);

test('oferece as regras de valor com o conversor acoplado', function () {
    $rules = FinancialRules::amount();

    expect($rules)->toContain('required')
        ->and($rules)->toContain('string')
        ->and($rules[2])->toBeInstanceOf(MoneyAmount::class);
});

test('oferece a chave de idempotência como UUID obrigatório, sem classe própria', function () {
    expect(FinancialRules::idempotencyKey())->toBe(['required', 'uuid']);
});

test('nomeia uma mensagem em português para cada regra que compõe', function () {
    expect(FinancialRules::messages())->toHaveKeys([
        'amount.required',
        'amount.string',
        'idempotency_key.required',
        'idempotency_key.uuid',
    ]);
});
