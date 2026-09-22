<?php

use App\Actions\CreateWallet;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;

/**
 * Os campos que o formulario de cadastro envia quando tudo esta certo.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function validRegistration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ana Ribeiro',
        'email' => 'ana@example.test',
        'password' => 'senha-bem-segura',
        'password_confirmation' => 'senha-bem-segura',
    ], $overrides);
}

test('mostra o formulário de cadastro para o visitante', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="_token"', false);
});

test('cria o usuário e a carteira juntos', function () {
    $this->post(route('register'), validRegistration());

    expect(User::count())->toBe(1)
        ->and(Wallet::count())->toBe(1);

    $wallet = User::first()->wallet;

    expect($wallet)->not->toBeNull()
        ->and($wallet->balance)->toBe(0);
});

test('autentica quem acabou de se cadastrar e redireciona para o painel', function () {
    $this->post(route('register'), validRegistration())
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::first());
});

test('guarda a senha com hash', function () {
    $this->post(route('register'), validRegistration());

    $user = User::first();

    expect($user->password)->not->toBe('senha-bem-segura')
        ->and(Hash::check('senha-bem-segura', $user->password))->toBeTrue();
});

test('recusa senha com menos de oito caracteres', function () {
    $this->post(route('register'), validRegistration([
        'password' => 'curta',
        'password_confirmation' => 'curta',
    ]))->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

test('recusa confirmação diferente da senha', function () {
    $this->post(route('register'), validRegistration([
        'password_confirmation' => 'outra-senha-segura',
    ]))->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

test('recusa e-mail já cadastrado', function () {
    $this->post(route('register'), validRegistration());
    $this->post(route('register'), validRegistration(['name' => 'Outra pessoa']));

    expect(User::count())->toBe(1)
        ->and(Wallet::count())->toBe(1);
});

test('descarta o usuário quando a carteira não pode ser criada', function () {
    $this->app->bind(CreateWallet::class, fn () => new class extends CreateWallet
    {
        /** Falha sempre, para provar que a transacao desfaz o usuario. */
        public function handle(User $user): Wallet
        {
            throw new RuntimeException('Falha proposital ao criar a carteira.');
        }
    });

    $this->post(route('register'), validRegistration());

    expect(User::count())->toBe(0)
        ->and(Wallet::count())->toBe(0);

    $this->assertGuest();
});
