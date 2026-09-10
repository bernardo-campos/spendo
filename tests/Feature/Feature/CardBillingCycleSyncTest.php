<?php

use App\Models\Card;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('installments start with estimated due dates and are updated to real due dates when billing cycles are loaded', function () {
    $user = User::factory()->create();

    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Test',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/transactions', [
            'type' => 'expense',
            'description' => 'Compra en cuotas',
            'amount' => 120000,
            'purchase_date' => '2026-03-08',
            'payment_method' => 'credit',
            'card_id' => $card->id,
            'installments_count' => 3,
            'tag_ids' => [],
        ])
        ->assertCreated();

    $plan = InstallmentPlan::query()->where('card_id', $card->id)->firstOrFail();

    $this->assertDatabaseHas('installments', [
        'installment_plan_id' => $plan->id,
        'installment_number' => 1,
        'due_date_is_estimated' => true,
    ]);

    $this->actingAs($user)
        ->postJson("/cards/{$card->id}/billing-cycles", [
            'closing_date' => '2026-03-10',
            'due_date' => '2026-04-17',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson("/cards/{$card->id}/billing-cycles", [
            'closing_date' => '2026-04-10',
            'due_date' => '2026-05-16',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson("/cards/{$card->id}/billing-cycles", [
            'closing_date' => '2026-05-10',
            'due_date' => '2026-06-17',
        ])
        ->assertCreated();

    $plan->refresh();

    expect($plan->first_due_date->toDateString())->toBe('2026-04-17');

    $installments = Installment::query()
        ->where('installment_plan_id', $plan->id)
        ->orderBy('installment_number')
        ->get();

    expect($installments[0]->due_date->toDateString())->toBe('2026-04-17')
        ->and($installments[0]->due_date_is_estimated)->toBeFalse()
        ->and($installments[1]->due_date->toDateString())->toBe('2026-05-16')
        ->and($installments[1]->due_date_is_estimated)->toBeFalse()
        ->and($installments[2]->due_date->toDateString())->toBe('2026-06-17')
        ->and($installments[2]->due_date_is_estimated)->toBeFalse();

    $transaction = Transaction::query()->findOrFail($plan->transaction_id);

    expect($transaction->payment_date->toDateString())->toBe('2026-04-17');
});

test('missing billing cycles do not shift installments to a later cycle', function () {
    $user = User::factory()->create();

    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Test',
        'last_four_digits' => '1234',
        'closing_day' => 27,
        'due_day' => 9,
        'is_active' => true,
    ]);

    $card->billingCycles()->create([
        'closing_date' => '2026-07-02',
        'due_date' => '2026-07-15',
    ]);

    $this->actingAs($user)
        ->postJson('/transactions', [
            'type' => 'expense',
            'description' => 'Compra histórica en cuotas',
            'amount' => 800000,
            'purchase_date' => '2025-11-22',
            'payment_method' => 'credit',
            'card_id' => $card->id,
            'installments_count' => 3,
            'tag_ids' => [],
        ])
        ->assertCreated();

    $installments = Installment::query()
        ->whereHas('installmentPlan', fn ($query) => $query->where('card_id', $card->id))
        ->orderBy('installment_number')
        ->get();

    expect($installments->pluck('due_date')->map->toDateString()->all())
        ->toBe(['2025-12-09', '2026-01-09', '2026-02-09']);
});

test('editing a purchase date rebuilds every pending installment when cycles are estimated', function () {
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Test',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/transactions', [
            'type' => 'expense',
            'description' => 'Compra en cuotas',
            'amount' => 120000,
            'purchase_date' => '2026-01-15',
            'payment_method' => 'credit',
            'card_id' => $card->id,
            'installments_count' => 3,
            'tag_ids' => [],
        ])
        ->assertCreated();

    $transaction = Transaction::query()->where('card_id', $card->id)->firstOrFail();

    $this->actingAs($user)
        ->putJson("/transactions/{$transaction->id}", ['purchase_date' => '2026-02-15'])
        ->assertSuccessful();

    $installments = $transaction->installmentPlan->installments()
        ->orderBy('installment_number')
        ->get();

    expect($installments->pluck('due_date')->map->toDateString()->all())
        ->toBe(['2026-04-20', '2026-05-20', '2026-06-20'])
        ->and($installments->pluck('due_date_is_estimated')->all())->toBe([true, true, true]);
});

test('editing a purchase date does not leave two installments in the same month when cycles are partially loaded', function () {
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Test',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);
    $card->billingCycles()->create([
        'closing_date' => '2026-03-10',
        'due_date' => '2026-04-17',
    ]);

    $this->actingAs($user)
        ->postJson('/transactions', [
            'type' => 'expense',
            'description' => 'Compra en cuotas',
            'amount' => 120000,
            'purchase_date' => '2026-01-15',
            'payment_method' => 'credit',
            'card_id' => $card->id,
            'installments_count' => 3,
            'tag_ids' => [],
        ])
        ->assertCreated();

    $transaction = Transaction::query()->where('card_id', $card->id)->firstOrFail();

    $this->actingAs($user)
        ->putJson("/transactions/{$transaction->id}", ['purchase_date' => '2026-02-15'])
        ->assertSuccessful();

    $installments = $transaction->installmentPlan->installments()
        ->orderBy('installment_number')
        ->get();

    expect($installments->pluck('due_date')->map->toDateString()->all())
        ->toBe(['2026-04-17', '2026-05-20', '2026-06-20'])
        ->and($installments->pluck('due_date')->map->format('Y-m')->unique()->count())->toBe(3)
        ->and($installments->pluck('due_date_is_estimated')->all())->toBe([false, true, true]);
});

test('changing the card updates the installment plan card and rebuilds its pending schedule', function () {
    $user = User::factory()->create();
    $originalCard = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Test',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);
    $newCard = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Mastercard Test',
        'last_four_digits' => '5678',
        'closing_day' => 5,
        'due_day' => 15,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/transactions', [
            'type' => 'expense',
            'description' => 'Compra en cuotas',
            'amount' => 120000,
            'purchase_date' => '2026-01-08',
            'payment_method' => 'credit',
            'card_id' => $originalCard->id,
            'installments_count' => 3,
            'tag_ids' => [],
        ])
        ->assertCreated();

    $transaction = Transaction::query()->where('card_id', $originalCard->id)->firstOrFail();

    $this->actingAs($user)
        ->putJson("/transactions/{$transaction->id}", ['card_id' => $newCard->id])
        ->assertSuccessful();

    $plan = $transaction->installmentPlan()->firstOrFail();
    $installments = $plan->installments()->orderBy('installment_number')->get();

    expect($plan->card_id)->toBe($newCard->id)
        ->and($installments->pluck('due_date')->map->toDateString()->all())
        ->toBe(['2026-03-15', '2026-04-15', '2026-05-15']);
});

test('a purchase date cannot be changed after an installment is paid', function () {
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Test',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/transactions', [
            'type' => 'expense',
            'description' => 'Compra en cuotas',
            'amount' => 120000,
            'purchase_date' => '2026-01-15',
            'payment_method' => 'credit',
            'card_id' => $card->id,
            'installments_count' => 3,
            'tag_ids' => [],
        ])
        ->assertCreated();

    $transaction = Transaction::query()->where('card_id', $card->id)->firstOrFail();
    $transaction->installmentPlan->installments()->firstOrFail()->update([
        'status' => 'paid',
        'paid_at' => '2026-03-20',
    ]);

    $this->actingAs($user)
        ->putJson("/transactions/{$transaction->id}", ['purchase_date' => '2026-02-15'])
        ->assertUnprocessable();

    expect($transaction->fresh()->purchase_date->toDateString())->toBe('2026-01-15');
});

test('an installment purchase cannot be changed to cash', function () {
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa Test',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/transactions', [
            'type' => 'expense',
            'description' => 'Compra en cuotas',
            'amount' => 120000,
            'purchase_date' => '2026-01-15',
            'payment_method' => 'credit',
            'card_id' => $card->id,
            'installments_count' => 3,
            'tag_ids' => [],
        ])
        ->assertCreated();

    $transaction = Transaction::query()->where('card_id', $card->id)->firstOrFail();

    $this->actingAs($user)
        ->putJson("/transactions/{$transaction->id}", ['payment_method' => 'cash'])
        ->assertUnprocessable();

    expect($transaction->fresh()->payment_method)->toBe('credit');
});
