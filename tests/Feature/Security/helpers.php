<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
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

/**
 * Um POST de transferencia como o formulario envia.
 *
 * @param  array<string, mixed>  $extra
 */
function postTransferencia(string $destinatario, array $extra = []): TestResponse
{
    return test()->post(route('transfers.store'), array_merge([
        'recipient_email' => $destinatario,
        'amount' => '1,00',
        'idempotency_key' => (string) Str::uuid7(),
    ], $extra));
}

/** Uma tentativa de login com o e-mail exatamente como foi digitado. */
function tentativaDeLogin(string $email, string $senha = 'senha-errada-qualquer'): TestResponse
{
    return test()->post(route('login'), ['email' => $email, 'password' => $senha]);
}

/*
 * As chaves abaixo repetem, de proposito, as que o `AppServiceProvider` monta.
 * O middleware guarda a cota sob o resumo do nome do limite com a chave, entao
 * limpar de verdade exige reproduzir os dois — e qualquer mudanca na chave real
 * derruba estes testes, que e o que se espera deles.
 */

/** A chave do limite de login, por IP e e-mail normalizado. */
function chaveDoLogin(string $email, string $ip = '127.0.0.1'): string
{
    return 'login|'.$ip.'|'.Str::lower(trim($email));
}

/** A chave do limite financeiro, por pessoa autenticada. */
function chaveFinanceira(int $userId): string
{
    return 'financial|'.$userId;
}

/** Zera a cota guardada para uma chave. */
function limpaLimite(string $limiter, string $chave): void
{
    RateLimiter::clear(md5($limiter.$chave));
}

/**
 * Pessoa com carteira e com a cota financeira zerada.
 *
 * O cache dos testes ja nasce vazio a cada caso, entao isto e cinto e
 * suspensorio: garante que nenhum teste dependa do driver de cache configurado
 * nem do que o teste anterior gastou.
 */
function usuarioComCotaLimpa(int $balance = 0): User
{
    $user = userWithWallet($balance);

    limpaLimite('financial', chaveFinanceira($user->id));

    return $user;
}

/**
 * Passa a escrever os logs num arquivo que o teste consegue reler.
 *
 * O canal e o proprio canal da aplicacao com o destino trocado: formato,
 * processors e nivel continuam sendo os de producao, e so o `php://stderr` vira
 * um arquivo temporario.
 */
function capturaLogs(): string
{
    $arquivo = tempnam(sys_get_temp_dir(), 'wallet-log-');

    config([
        'logging.channels.prova' => array_replace(config('logging.channels.stderr'), [
            'handler_with' => ['stream' => $arquivo],
        ]),
        'logging.default' => 'prova',
    ]);

    return $arquivo;
}

/**
 * As linhas do log ja decodificadas; JSON invalido derruba o teste aqui.
 *
 * @return list<array<string, mixed>>
 */
function linhasDeLog(string $arquivo): array
{
    $linhas = array_filter(explode("\n", (string) file_get_contents($arquivo)));

    return array_values(array_map(
        fn (string $linha) => json_decode($linha, true, flags: JSON_THROW_ON_ERROR),
        $linhas,
    ));
}

/**
 * Só as linhas das operações financeiras, opcionalmente de um tipo.
 *
 * @return list<array<string, mixed>>
 */
function logsFinanceiros(string $arquivo, ?string $tipo = null): array
{
    return array_values(array_filter(
        linhasDeLog($arquivo),
        fn (array $linha) => str_starts_with((string) $linha['message'], 'operação financeira')
            && ($tipo === null || ($linha['context']['type'] ?? null) === $tipo),
    ));
}

/**
 * O contexto de cada linha financeira, que é onde estão os identificadores.
 *
 * @return list<array<string, mixed>>
 */
function contextosFinanceiros(string $arquivo, ?string $tipo = null): array
{
    return array_map(fn (array $linha) => $linha['context'], logsFinanceiros($arquivo, $tipo));
}
