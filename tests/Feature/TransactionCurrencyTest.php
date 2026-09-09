<?php

use App\Enums\TransactionCurrency;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('transactions default to Argentine pesos at the database and model layers', function () {
    $user = User::factory()->create();

    DB::table('transactions')->insert([
        'user_id' => $user->id,
        'type' => 'income',
        'description' => 'Ingreso existente',
        'amount' => 1000,
        'purchase_date' => '2026-09-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $transaction = Transaction::query()->firstOrFail();

    expect($transaction->currency)->toBe(TransactionCurrency::ArgentinePeso);
});

test('api stores and returns the selected transaction currency', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/transactions', [
        'type' => 'income',
        'description' => 'Cobro en dólares',
        'amount' => 250,
        'currency' => 'USD',
        'purchase_date' => '2026-09-01',
    ])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'USD');

    expect(Transaction::query()->sole()->currency)->toBe(TransactionCurrency::UnitedStatesDollar);
});

test('api defaults an omitted transaction currency to Argentine pesos and rejects unsupported currencies', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/transactions', [
        'type' => 'income',
        'description' => 'Cobro local',
        'amount' => 250,
        'purchase_date' => '2026-09-01',
    ])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'ARS');

    $this->postJson('/api/v1/transactions', [
        'type' => 'income',
        'description' => 'Moneda no admitida',
        'amount' => 250,
        'currency' => 'EUR',
        'purchase_date' => '2026-09-01',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});

test('installment plans expose the currency inherited from their transaction', function () {
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'description' => 'Compra en dólares',
        'amount' => 1200,
        'currency' => 'USD',
        'purchase_date' => '2026-09-01',
        'payment_method' => 'credit',
        'card_id' => $card->id,
        'installments_count' => 2,
    ])->assertCreated();

    $this->getJson('/api/v1/installment-plans')
        ->assertSuccessful()
        ->assertJsonPath('data.0.transaction.currency', 'USD');
});

test('dashboard returns totals separately for each currency', function () {
    Carbon::setTestNow('2026-09-15');

    try {
        $user = User::factory()->create();

        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'income',
            'description' => 'Ingreso en pesos',
            'amount' => 1000,
            'currency' => TransactionCurrency::ArgentinePeso,
            'purchase_date' => '2026-09-02',
        ]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'description' => 'Gasto en pesos',
            'amount' => 250,
            'currency' => TransactionCurrency::ArgentinePeso,
            'purchase_date' => '2026-09-03',
        ]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'income',
            'description' => 'Ingreso en dólares',
            'amount' => 100,
            'currency' => TransactionCurrency::UnitedStatesDollar,
            'purchase_date' => '2026-09-04',
        ]);

        $this->actingAs($user)
            ->getJson('/dashboard')
            ->assertSuccessful()
            ->assertJsonPath('totals.ARS.income', '1000.00')
            ->assertJsonPath('totals.ARS.expense', '250.00')
            ->assertJsonPath('totals.ARS.net', '750.00')
            ->assertJsonPath('totals.USD.income', '100.00')
            ->assertJsonPath('totals.USD.expense', '0.00')
            ->assertJsonPath('totals.USD.net', '100.00');
    } finally {
        Carbon::setTestNow();
    }
});
