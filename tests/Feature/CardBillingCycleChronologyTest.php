<?php

use App\Models\Card;
use App\Models\RecurringExpense;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardPaymentDateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return list<array{closing_date:string,due_date:string}>
 */
function bankBillingCycles(): array
{
    return [
        ['closing_date' => '2026-05-28', 'due_date' => '2026-06-10'],
        ['closing_date' => '2026-07-02', 'due_date' => '2026-07-15'],
        ['closing_date' => '2026-07-30', 'due_date' => '2026-08-12'],
        ['closing_date' => '2026-08-27', 'due_date' => '2026-09-09'],
        ['closing_date' => '2026-10-01', 'due_date' => '2026-10-14'],
        ['closing_date' => '2026-10-29', 'due_date' => '2026-11-11'],
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->card = Card::query()->create([
        'user_id' => $this->user->id, 'name' => 'Visa', 'last_four_digits' => '1234',
        'closing_day' => 27, 'due_day' => 9, 'is_active' => true,
    ]);
    $this->actingAs($this->user);
});

test('all supplied bank cycles can be registered in reverse order through web and API routes', function (string $prefix) {
    foreach (array_reverse(bankBillingCycles()) as $cycle) {
        $this->postJson("{$prefix}/cards/{$this->card->id}/billing-cycles", $cycle)->assertCreated();
    }

    expect($this->card->billingCycles()->orderBy('closing_date')->get()
        ->map(fn ($cycle): array => [
            'closing_date' => $cycle->closing_date->toDateString(),
            'due_date' => $cycle->due_date->toDateString(),
        ])->all())->toBe(bankBillingCycles());
})->with(['', '/api/v1']);

test('purchases use consecutive real closings including closing day boundaries and months without a closing', function (string $purchase, string $due) {
    $this->card->billingCycles()->createMany(bankBillingCycles());
    $service = app(CardPaymentDateService::class);

    expect($service->resolve($purchase, 'credit', $this->card))
        ->toBe(['date' => $due, 'is_estimated' => false]);
})->with([
    ['2026-05-28', '2026-06-10'],
    ['2026-05-29', '2026-07-15'],
    ['2026-06-30', '2026-07-15'],
    ['2026-07-02', '2026-07-15'],
    ['2026-07-03', '2026-08-12'],
    ['2026-09-30', '2026-10-14'],
    ['2026-10-01', '2026-10-14'],
    ['2026-10-02', '2026-11-11'],
    ['2026-10-29', '2026-11-11'],
]);

test('installments follow every real cycle and paid installments stay unchanged during synchronization', function () {
    $this->card->update(['closing_day' => 15]);
    $this->card->billingCycles()->createMany(bankBillingCycles());
    $this->postJson('/transactions', [
        'type' => 'expense', 'description' => 'Compra en cuotas', 'amount' => 6000,
        'purchase_date' => '2026-05-20', 'payment_method' => 'credit',
        'card_id' => $this->card->id, 'installments_count' => 6,
    ])->assertCreated();
    $transaction = Transaction::query()->sole();
    $installments = $transaction->installmentPlan->installments()->orderBy('installment_number')->get();

    expect($installments->pluck('due_date')->map->toDateString()->all())
        ->toBe(array_column(bankBillingCycles(), 'due_date'))
        ->and($installments->pluck('due_date_is_estimated')->all())->toBe(array_fill(0, 6, false));

    $installments[0]->update(['status' => 'paid', 'paid_at' => '2026-06-10']);
    $cycle = $this->card->billingCycles()->whereDate('closing_date', '2026-05-28')->sole();
    $this->patchJson("/cards/{$this->card->id}/billing-cycles/{$cycle->id}", ['due_date' => '2026-06-11'])
        ->assertSuccessful();
    expect($installments[0]->fresh()->due_date->toDateString())->toBe('2026-06-10');
});

test('adding the previous closing corrects existing expenses and subscription previews', function () {
    $this->card->billingCycles()->create(['closing_date' => '2026-10-29', 'due_date' => '2026-11-11']);
    foreach (['2026-10-01', '2026-10-02'] as $purchase) {
        $this->postJson('/transactions', [
            'type' => 'expense', 'description' => 'Compra', 'amount' => 1000,
            'purchase_date' => $purchase, 'payment_method' => 'credit', 'card_id' => $this->card->id,
            'installments_count' => 1,
        ])->assertCreated();
    }
    RecurringExpense::factory()->for($this->user)->create([
        'payment_method' => 'credit', 'card_id' => $this->card->id,
        'day_of_month' => 1, 'starts_on' => '2026-10-01',
    ]);
    $this->postJson("/cards/{$this->card->id}/billing-cycles", [
        'closing_date' => '2026-10-01', 'due_date' => '2026-10-14',
    ])->assertCreated();

    expect(Transaction::query()->orderBy('purchase_date')->get()->pluck('payment_date')->map->toDateString()->all())
        ->toBe(['2026-10-14', '2026-11-11']);
    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.payment_date', '2026-10-14');
});

test('an early real closing does not remove the following estimated cycle', function () {
    $this->card->billingCycles()->create(['closing_date' => '2026-07-02', 'due_date' => '2026-07-15']);
    $schedule = app(CardPaymentDateService::class)->schedule('2026-06-15', $this->card, 3);

    expect(array_column($schedule, 'due_date'))->toBe(['2026-07-15', '2026-08-09', '2026-09-09'])
        ->and(array_column($schedule, 'is_estimated'))->toBe([false, true, true]);
});
