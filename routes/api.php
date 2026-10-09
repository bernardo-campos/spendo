<?php

use App\Http\Controllers\Api\V1\CardBillingCycleController;
use App\Http\Controllers\Api\V1\CardController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CurrencyExchangeController;
use App\Http\Controllers\Api\V1\InstallmentPlanController;
use App\Http\Controllers\Api\V1\RecurringExpenseController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->name('api.v1.')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('tags', TagController::class);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('cards.billing-cycles', CardBillingCycleController::class)
        ->parameters(['billing-cycles' => 'billingCycle']);
    Route::get('transactions/places', [TransactionController::class, 'places']);
    Route::get('currency-exchanges/latest', [CurrencyExchangeController::class, 'latest']);
    Route::apiResource('currency-exchanges', CurrencyExchangeController::class)->except('index');
    Route::apiResource('transactions', TransactionController::class);
    Route::get('recurring-expenses/preview', [RecurringExpenseController::class, 'preview'])->name('recurring-expenses.preview');
    Route::post('recurring-expenses/{recurringExpense}/decisions', [RecurringExpenseController::class, 'decide'])->name('recurring-expenses.decide');
    Route::put('recurring-expenses/{recurringExpense}/decisions', [RecurringExpenseController::class, 'saveOccurrence'])->name('recurring-expenses.save-occurrence');
    Route::apiResource('recurring-expenses', RecurringExpenseController::class)->only(['index', 'store', 'update']);
    Route::apiResource('installment-plans', InstallmentPlanController::class);
});
