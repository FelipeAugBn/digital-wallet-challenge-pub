<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ReversalController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

    // Cinco tentativas por minuto para cada par de IP e e-mail; a chave esta
    // no `AppServiceProvider`.
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/extrato', StatementController::class)->name('statement');

    Route::get('/deposits', [DepositController::class, 'create'])->name('deposits.create');
    Route::get('/transfers', [TransferController::class, 'create'])->name('transfers.create');

    // A cota de vinte por minuto e so do que movimenta dinheiro. Abrir o
    // painel, paginar o extrato, pedir um formulario ou sair da conta nao
    // gasta nada dela: quem esta conferindo o saldo nao disputa espaco com
    // quem esta depositando.
    Route::middleware('throttle:financial')->group(function () {
        Route::post('/deposits', [DepositController::class, 'store'])->name('deposits.store');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('/transactions/{transaction}/reversals', ReversalController::class)->name('reversals.store');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
