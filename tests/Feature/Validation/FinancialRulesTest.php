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

test('aprova uma operação bem formada', function () {
    $validator = validateFinancialInput([
        'amount' => '1.000,50',
        'idempotency_key' => (string) Str::uuid7(),
    ]);

    expect($validator->passes())->toBeTrue();
});

test('reclama em português quando os campos faltam', function () {
    $validator = validateFinancialInput([]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('amount'))->toBe('Informe o valor.')
        ->and($validator->errors()->first('idempotency_key'))
        ->toBe('Não foi possível confirmar a operação. Recarregue a página e tente de novo.');
});

test('reclama do formato do valor usando a regra de dinheiro', function () {
    $validator = validateFinancialInput([
        'amount' => '1000.50',
        'idempotency_key' => (string) Str::uuid7(),
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('amount'))
        ->toBe('Informe um valor entre R$ 0,01 e R$ 1.000.000,00, no formato 1.000,50.');
});

test('recusa chave de idempotência que não é UUID', function () {
    $validator = validateFinancialInput([
        'amount' => '10,50',
        'idempotency_key' => 'chave-qualquer',
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('idempotency_key'))
        ->toBe('Não foi possível confirmar a operação. Recarregue a página e tente de novo.');
});
