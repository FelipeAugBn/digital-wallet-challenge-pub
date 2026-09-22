<?php

use App\Actions\TransferMoney;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;

// `userWithWallet`, `walletOf`, `persistedTransaction` e `deposit` ja existem e
// valem para qualquer operacao financeira; a transferencia usa as mesmas em vez
// de redeclarar quatro funcoes iguais.
require_once __DIR__.'/../Deposit/helpers.php';

/**
 * Chama a Action pelo container, como o controller faz.
 *
 * O destinatario pode vir como pessoa, para o caminho normal, ou como texto,
 * para exercitar um e-mail que nao pertence a ninguem.
 */
function transfer(User $sender, User|string $recipient, string $amount, ?string $key = null): Transaction
{
    return app(TransferMoney::class)->handle(
        $sender,
        $recipient instanceof User ? $recipient->email : $recipient,
        Money::fromInput($amount),
        $key ?? (string) Str::uuid7(),
    );
}

/** O padrao de UUIDv7 que os formularios financeiros precisam trazer. */
const UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

/** Le a chave escondida no HTML do formulario, ou nada quando ela nao existe. */
function hiddenKey(string $html): ?string
{
    return preg_match('/name="idempotency_key" value="([^"]*)"/', $html, $achado) === 1
        ? $achado[1]
        : null;
}
