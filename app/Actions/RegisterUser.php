<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    /** A Action vem pelo construtor para o teste poder troca-la no container. */
    public function __construct(private readonly CreateWallet $createWallet) {}

    /**
     * Cria a pessoa e a carteira na mesma transacao: se a carteira falhar,
     * o usuario tambem nao fica no banco.
     *
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);

            $this->createWallet->handle($user);

            return $user;
        });
    }
}
