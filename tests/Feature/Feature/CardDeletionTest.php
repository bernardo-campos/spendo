<?php

use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('card with associated transactions cannot be deleted', function () {
    $user = User::factory()->create();

    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Banco Test',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'category_id' => null,
        'payment_method' => 'credit',
        'card_id' => $card->id,
        'type' => 'expense',
        'description' => 'Compra con tarjeta',
        'amount' => 15000,
        'purchase_date' => '2026-03-10',
        'payment_date' => '2026-04-20',
        'notes' => null,
    ]);

    $this->actingAs($user)
        ->deleteJson("/cards/{$card->id}")
        ->assertStatus(422)
        ->assertJson([
            'message' => 'No se puede eliminar una tarjeta asociada a transacciones.',
        ]);

    $this->assertDatabaseHas('cards', ['id' => $card->id]);
});

test('card without associated transactions can be deleted', function () {
    $user = User::factory()->create();

    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Master Banco Test',
        'last_four_digits' => '4321',
        'closing_day' => 8,
        'due_day' => 18,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->deleteJson("/cards/{$card->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('cards', ['id' => $card->id]);
});
