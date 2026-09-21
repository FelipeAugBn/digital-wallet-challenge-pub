<?php

use Illuminate\Support\Facades\DB;

it('runs against the PostgreSQL test database', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('wallet_testing');
});

it('writes to a database that was migrated for the suite', function () {
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

it('discards rows written by the previous test', function () {
    expect(DB::table('users')->count())->toBe(0);
});
