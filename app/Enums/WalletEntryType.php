<?php

namespace App\Enums;

/**
 * O lado do lancamento no livro-razao: credito entra, debito sai.
 *
 * Espelha o CHECK `wallet_entries_type_valid`.
 */
enum WalletEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
