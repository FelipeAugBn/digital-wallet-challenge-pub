<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Wallet;

class CreateWallet
{
    /** Cria a carteira da pessoa; o saldo inicial vem do default do banco. */
    public function handle(User $user): Wallet
    {
        return $user->wallet()->create()->refresh();
    }
}
