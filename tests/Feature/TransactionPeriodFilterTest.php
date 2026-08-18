<?php

use App\Models\Card;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('transactions are filtered by the requested period', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $incomeInPeriod = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'income',
        'description' => 'Ingreso de marzo',
        'amount' => 1000,
        'purchase_date' => '2026-03-15',
        'payment_date' => '2026-03-15',
    ]);

    $cashExpenseInPeriod = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'expense',
        'description' => 'Gasto pagado en marzo',
        'amount' => 200,
        'purchase_date' => '2026-02-28',
        'payment_date' => '2026-03-02',
        'payment_method' => 'cash',
    ]);

    $creditCard = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
        'is_active' => true,
    ]);

    $installmentTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'card_id' => $creditCard->id,
        'type' => 'expense',
        'description' => 'Compra en cuotas',
        'amount' => 600,
        'purchase_date' => '2026-01-15',
        'payment_date' => '2026-02-20',
        'payment_method' => 'credit',
    ]);

    $installmentPlan = InstallmentPlan::query()->create([
        'user_id' => $user->id,
        'transaction_id' => $installmentTransaction->id,
        'card_id' => $creditCard->id,
        'installments_count' => 3,
        'total_amount' => 600,
        'first_due_date' => '2026-02-20',
        'status' => 'pending',
    ]);

    Installment::query()->create([
        'installment_plan_id' => $installmentPlan->id,
        'installment_number' => 2,
        'amount' => 200,
        'due_date' => '2026-03-20',
        'due_date_is_estimated' => false,
        'status' => 'pending',
    ]);

    $outsidePeriod = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'income',
        'description' => 'Ingreso de abril',
        'amount' => 900,
        'purchase_date' => '2026-04-01',
        'payment_date' => '2026-04-01',
    ]);

    $otherUserTransaction = Transaction::query()->create([
        'user_id' => $otherUser->id,
        'type' => 'income',
        'description' => 'Ingreso ajeno',
        'amount' => 500,
        'purchase_date' => '2026-03-10',
        'payment_date' => '2026-03-10',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/transactions?period=2026-03')
        ->assertSuccessful();

    $transactionIds = collect($response->json())->pluck('id');

    expect($transactionIds)
        ->toContain($incomeInPeriod->id, $cashExpenseInPeriod->id, $installmentTransaction->id)
        ->not->toContain($outsidePeriod->id, $otherUserTransaction->id);
});

test('transactions require a valid period', function (string $period) {
    $user = User::factory()->create();
    $url = $period === '' ? '/transactions' : '/transactions?period='.urlencode($period);

    $this->actingAs($user)
        ->getJson($url)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['period']);
})->with([
    'missing period' => '',
    'invalid period' => 'March 2026',
]);
