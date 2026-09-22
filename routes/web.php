<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
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
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/extrato', StatementController::class)->name('statement');

    Route::get('/deposits', [DepositController::class, 'create'])->name('deposits.create');
    Route::post('/deposits', [DepositController::class, 'store'])->name('deposits.store');

    Route::get('/transfers', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
