<?php

use Illuminate\Support\Facades\DB;

test('roda no banco de testes do PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('wallet_testing');
});

test('grava num banco já migrado para a suíte', function () {
    expect(DB::table('users')->count())->toBe(0);

    DB::table('users')->insert([
        'name' => 'Isolation probe',
        'email' => 'probe@example.test',
        'password' => 'irrelevant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('users')->count())->toBe(1);
});

test('descarta as linhas gravadas pelo teste anterior', function () {
    expect(DB::table('users')->count())->toBe(0);
});
