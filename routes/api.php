<?php

use App\Http\Controllers\Api\V1\CardBillingCycleController;
use App\Http\Controllers\Api\V1\CardController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\InstallmentPlanController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->name('api.v1.')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('tags', TagController::class);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('cards.billing-cycles', CardBillingCycleController::class)
        ->parameters(['billing-cycles' => 'billingCycle']);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('installment-plans', InstallmentPlanController::class);
});
