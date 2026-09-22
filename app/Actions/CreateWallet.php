<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Wallet;

class CreateWallet
{
    public function handle(User $user): Wallet
    {
        return $user->wallet()->create()->refresh();
    }
}
