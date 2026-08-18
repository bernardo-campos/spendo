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
