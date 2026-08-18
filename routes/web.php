<?php

use App\Http\Controllers\Api\V1\CardBillingCycleController;
use App\Http\Controllers\Api\V1\CardController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\InstallmentPlanController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('app') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/app', 'app')->name('app');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('tags', TagController::class);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('cards.billing-cycles', CardBillingCycleController::class)
        ->parameters(['billing-cycles' => 'billingCycle']);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('installment-plans', InstallmentPlanController::class);
});
