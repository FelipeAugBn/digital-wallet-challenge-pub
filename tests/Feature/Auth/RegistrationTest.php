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

it('shows the registration form to a visitor', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="_token"', false);
});

it('creates the user and the wallet together', function () {
    $this->post(route('register'), validRegistration());

    expect(User::count())->toBe(1)
        ->and(Wallet::count())->toBe(1);

    $wallet = User::first()->wallet;

    expect($wallet)->not->toBeNull()
        ->and($wallet->balance)->toBe(0);
});

it('signs the new user in and redirects to the dashboard', function () {
    $this->post(route('register'), validRegistration())
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::first());
});

it('stores the password hashed', function () {
    $this->post(route('register'), validRegistration());

    $user = User::first();

    expect($user->password)->not->toBe('senha-bem-segura')
        ->and(Hash::check('senha-bem-segura', $user->password))->toBeTrue();
});

it('rejects a password shorter than eight characters', function () {
    $this->post(route('register'), validRegistration([
        'password' => 'curta',
        'password_confirmation' => 'curta',
    ]))->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

it('rejects a confirmation that does not match the password', function () {
    $this->post(route('register'), validRegistration([
        'password_confirmation' => 'outra-senha-segura',
    ]))->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

it('rejects an email that is already registered', function () {
    $this->post(route('register'), validRegistration());
    $this->post(route('register'), validRegistration(['name' => 'Outra pessoa']));

    expect(User::count())->toBe(1)
        ->and(Wallet::count())->toBe(1);
});

it('discards the user when the wallet cannot be created', function () {
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
