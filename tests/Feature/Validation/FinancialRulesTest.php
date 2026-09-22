<?php

use App\Rules\FinancialRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Monta o validador do jeito que o Form Request da proxima tarefa vai montar,
 * para conferir as regras dentro do framework e nao so isoladas.
 */
function validateFinancialInput(array $data): Illuminate\Validation\Validator
{
    return Validator::make($data, [
        'amount' => FinancialRules::amount(),
        'idempotency_key' => FinancialRules::idempotencyKey(),
    ], FinancialRules::messages());
}

it('approves a well formed operation', function () {
    $validator = validateFinancialInput([
        'amount' => '1.000,50',
        'idempotency_key' => (string) Str::uuid7(),
    ]);

    expect($validator->passes())->toBeTrue();
});

it('complains in portuguese when the fields are missing', function () {
    $validator = validateFinancialInput([]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('amount'))->toBe('Informe o valor.')
        ->and($validator->errors()->first('idempotency_key'))
        ->toBe('Não foi possível confirmar a operação. Recarregue a página e tente de novo.');
});

it('complains about the amount format using the money rule', function () {
    $validator = validateFinancialInput([
        'amount' => '1000.50',
        'idempotency_key' => (string) Str::uuid7(),
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('amount'))
        ->toBe('Informe um valor entre R$ 0,01 e R$ 1.000.000,00, no formato 1.000,50.');
});

it('refuses an idempotency key that is not a uuid', function () {
    $validator = validateFinancialInput([
        'amount' => '10,50',
        'idempotency_key' => 'chave-qualquer',
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('idempotency_key'))
        ->toBe('Não foi possível confirmar a operação. Recarregue a página e tente de novo.');
});
