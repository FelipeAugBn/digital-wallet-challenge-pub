<?php

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// `userWithWallet`, `walletOf`, `deposit`, `transfer` e o padrao `UUID_V7` ja
// existem para as operacoes financeiras; a suite de seguranca reusa os mesmos.
require_once __DIR__.'/../Transfer/helpers.php';

/**
 * Um POST de deposito como o formulario envia.
 *
 * @param  array<string, mixed>  $extra
 */
function postDeposito(array $extra = []): TestResponse
{
    return test()->post(route('deposits.store'), array_merge([
        'amount' => '1,00',
        'idempotency_key' => (string) Str::uuid7(),
    ], $extra));
}

/** Uma tentativa de login com o e-mail exatamente como foi digitado. */
function tentativaDeLogin(string $email, string $senha = 'senha-errada-qualquer'): TestResponse
{
    return test()->post(route('login'), ['email' => $email, 'password' => $senha]);
}
