<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('an expense can store and clear an optional place', function () {
    $user = User::factory()->create();

    $created = $this->actingAs($user)->postJson('/transactions', [
        'type' => 'expense',
        'description' => 'Compras semanales',
        'place' => 'Changomas',
        'amount' => 12500,
        'purchase_date' => '2026-09-18',
        'payment_method' => 'cash',
    ]);

    $transactionId = $created
        ->assertCreated()
        ->assertJsonPath('place', 'Changomas')
        ->json('id');

    $this->putJson("/transactions/{$transactionId}", ['place' => null])
        ->assertSuccessful()
        ->assertJsonPath('place', null);

    expect(Transaction::query()->findOrFail($transactionId)->place)->toBeNull();
});

test('places include each expense place once for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Transaction::query()->insert([
        [
            'user_id' => $user->id,
            'type' => 'expense',
            'description' => 'Supermercado',
            'place' => 'Changomas',
            'amount' => 1000,
            'purchase_date' => '2026-09-18',
            'payment_date' => '2026-09-18',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $user->id,
            'type' => 'expense',
            'description' => 'Supermercado',
            'place' => 'Changomas',
            'amount' => 2000,
            'purchase_date' => '2026-09-17',
            'payment_date' => '2026-09-17',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $user->id,
            'type' => 'expense',
            'description' => 'Verdulería',
            'place' => 'GreenMarket',
            'amount' => 500,
            'purchase_date' => '2026-09-16',
            'payment_date' => '2026-09-16',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $user->id,
            'type' => 'income',
            'description' => 'Ingreso',
            'place' => 'No debe aparecer',
            'amount' => 5000,
            'purchase_date' => '2026-09-16',
            'payment_date' => '2026-09-16',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'description' => 'Ajeno',
            'place' => 'LM Alvarado',
            'amount' => 500,
            'purchase_date' => '2026-09-16',
            'payment_date' => '2026-09-16',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($user)->getJson('/transactions/places')
        ->assertSuccessful()
        ->assertExactJson(['Changomas', 'GreenMarket']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'description' => 'Compra API',
        'place' => 'Super',
        'amount' => 1200,
        'purchase_date' => '2026-09-18',
        'payment_method' => 'cash',
    ])
        ->assertCreated()
        ->assertJsonPath('data.place', 'Super');
});
