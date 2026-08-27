<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('api v1 resources require Sanctum authentication', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    '/api/v1/categories',
    '/api/v1/tags',
    '/api/v1/cards',
    '/api/v1/transactions',
    '/api/v1/installment-plans',
]);

test('api v1 accepts a Sanctum bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-v1-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/categories')
        ->assertSuccessful();
});

test('api v1 returns paginated resources without internal ownership fields', function () {
    $user = User::factory()->create();

    foreach (range(1, 16) as $number) {
        Category::query()->create([
            'user_id' => $user->id,
            'name' => "Categoría {$number}",
            'slug' => "categoria-{$number}",
            'scope' => 'expense',
            'is_active' => true,
        ]);
    }

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/categories')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'scope', 'is_active', 'created_at', 'updated_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonMissing(['user_id' => $user->id]);
});

test('api v1 validates input and isolates resources by user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Ajena',
        'slug' => 'ajena',
        'scope' => 'expense',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/categories', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'slug', 'scope']);

    $this->getJson("/api/v1/categories/{$category->id}")->assertNotFound();
});

test('api v1 exposes the CRUD resource families', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/tags', [
        'name' => 'Hogar',
        'slug' => 'hogar',
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'hogar');

    $cardResponse = $this->postJson('/api/v1/cards', [
        'name' => 'Visa',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ])->assertCreated();

    $cardId = $cardResponse->json('data.id');

    $this->postJson("/api/v1/cards/{$cardId}/billing-cycles", [
        'closing_date' => '2026-03-10',
        'due_date' => '2026-03-20',
    ])
        ->assertCreated()
        ->assertJsonPath('data.card_id', $cardId);

    $transactionResponse = $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'description' => 'Compra',
        'amount' => 1200,
        'purchase_date' => '2026-03-08',
        'payment_method' => 'cash',
    ])->assertCreated();

    $this->getJson('/api/v1/transactions/'.$transactionResponse->json('data.id'))
        ->assertSuccessful()
        ->assertJsonMissing(['user_id' => $user->id]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'card_id' => $cardId,
        'type' => 'expense',
        'description' => 'Compra financiada',
        'amount' => 2400,
        'purchase_date' => '2026-03-08',
        'payment_date' => '2026-03-20',
        'payment_method' => 'credit',
    ]);

    $planResponse = $this->postJson('/api/v1/installment-plans', [
        'transaction_id' => $transaction->id,
        'card_id' => $cardId,
        'installments_count' => 2,
        'first_due_date' => '2026-03-20',
    ])->assertCreated();

    $this->patchJson('/api/v1/tags/'.$this->getJson('/api/v1/tags')->json('data.0.id'), [
        'name' => 'Hogar actualizado',
        'slug' => 'hogar-actualizado',
    ])->assertSuccessful();

    $this->deleteJson('/api/v1/installment-plans/'.$planResponse->json('data.id'))->assertNoContent();
});

test('billing cycle dates are serialized without a time or timezone', function () {
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    $card->billingCycles()->create([
        'closing_date' => '2026-03-10',
        'due_date' => '2026-03-20',
    ]);

    $this->actingAs($user)
        ->getJson('/cards')
        ->assertSuccessful()
        ->assertJsonPath('0.billing_cycles.0.closing_date', '2026-03-10')
        ->assertJsonPath('0.billing_cycles.0.due_date', '2026-03-20');

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/cards/{$card->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.billing_cycles.0.closing_date', '2026-03-10')
        ->assertJsonPath('data.billing_cycles.0.due_date', '2026-03-20');
});
