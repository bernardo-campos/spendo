<?php

use App\Models\Category;
use App\Models\CurrencyExchange;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function exchangePayload(array $overrides = []): array
{
    return array_replace([
        'source_currency' => 'ARS', 'source_amount' => '1500.00',
        'target_currency' => 'USD', 'target_amount' => '1.00',
        'purchase_date' => '2026-09-15', 'description' => 'Compra de dólares',
        'place' => 'Mercado Pago',
    ], $overrides);
}

test('an exchange creates two linked legs and affects both monthly totals', function () {
    Carbon::setTestNow('2026-09-20');
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/currency-exchanges', exchangePayload())->assertCreated();
    $exchangeId = $response->json('id');

    expect(Transaction::query()->where('exchange_id', $exchangeId)->count())->toBe(2);
    $response->assertJsonPath('expense.type', 'expense')
        ->assertJsonPath('expense.currency', 'ARS')
        ->assertJsonPath('expense.amount', '1500.00')
        ->assertJsonPath('income.type', 'income')
        ->assertJsonPath('income.currency', 'USD')
        ->assertJsonPath('income.amount', '1.00');
    $this->getJson('/transactions?period=2026-09')->assertSuccessful()->assertJsonCount(2);
    $this->getJson('/dashboard')->assertSuccessful()
        ->assertJsonPath('totals.ARS.expense', '1500.00')
        ->assertJsonPath('totals.USD.income', '1.00');
    Carbon::setTestNow();
});

test('exchange legs can only be edited or deleted together', function () {
    $user = User::factory()->create();
    $exchangeId = $this->actingAs($user)->postJson('/currency-exchanges', exchangePayload())->json('id');
    $expense = Transaction::query()->where('exchange_id', $exchangeId)->where('type', 'expense')->firstOrFail();

    $this->putJson("/transactions/{$expense->id}", ['amount' => 2])->assertUnprocessable();
    $this->deleteJson("/transactions/{$expense->id}")->assertUnprocessable();
    $this->putJson("/currency-exchanges/{$exchangeId}", exchangePayload(['source_amount' => '3000.00', 'target_amount' => '2.00']))
        ->assertSuccessful()->assertJsonPath('income.amount', '2.00');
    expect(Transaction::query()->where('exchange_id', $exchangeId)->count())->toBe(2);

    $this->deleteJson("/currency-exchanges/{$exchangeId}")->assertNoContent();
    expect(CurrencyExchange::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

test('an exchange saves its expense category and shares tags between both legs', function () {
    $user = User::factory()->create();
    $category = Category::query()->create(['user_id' => $user->id, 'name' => 'Ahorro', 'slug' => 'ahorro', 'scope' => 'expense']);
    $tag = Tag::query()->create(['user_id' => $user->id, 'name' => 'Dólares', 'slug' => 'dolares']);
    $exchangeId = $this->actingAs($user)->postJson('/currency-exchanges', exchangePayload([
        'category_id' => $category->id, 'tag_ids' => [$tag->id],
    ]))->assertCreated()
        ->assertJsonPath('expense.category_id', $category->id)
        ->assertJsonPath('expense.tags.0.id', $tag->id)
        ->assertJsonPath('income.category_id', null)
        ->assertJsonPath('income.tags.0.id', $tag->id)
        ->json('id');

    $this->putJson("/currency-exchanges/{$exchangeId}", exchangePayload([
        'category_id' => null, 'tag_ids' => [],
    ]))->assertSuccessful()->assertJsonPath('expense.category_id', null)
        ->assertJsonCount(0, 'expense.tags')->assertJsonCount(0, 'income.tags');
});

test('exchange categories and tags must belong to the user and category must support expenses', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $incomeCategory = Category::query()->create(['user_id' => $user->id, 'name' => 'Sueldo', 'slug' => 'sueldo', 'scope' => 'income']);
    $otherTag = Tag::query()->create(['user_id' => $other->id, 'name' => 'Ajeno', 'slug' => 'ajeno']);

    $this->actingAs($user)->postJson('/currency-exchanges', exchangePayload([
        'category_id' => $incomeCategory->id, 'tag_ids' => [$otherTag->id],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'tag_ids.0']);
    expect(CurrencyExchange::query()->count())->toBe(0);
});

test('exchange input is validated and other users cannot read or change it', function () {
    $owner = User::factory()->create();
    $exchangeId = $this->actingAs($owner)->postJson('/currency-exchanges', exchangePayload())->json('id');

    $this->postJson('/currency-exchanges', exchangePayload(['target_currency' => 'ARS', 'target_amount' => '1.001']))
        ->assertUnprocessable()->assertJsonValidationErrors(['target_currency', 'target_amount']);
    expect(CurrencyExchange::query()->count())->toBe(1);

    $this->actingAs(User::factory()->create());
    $this->getJson("/currency-exchanges/{$exchangeId}")->assertNotFound();
    $this->putJson("/currency-exchanges/{$exchangeId}", exchangePayload())->assertNotFound();
    $this->deleteJson("/currency-exchanges/{$exchangeId}")->assertNotFound();
});

test('latest suggestion uses the most recent exchange in either direction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->postJson('/currency-exchanges', exchangePayload())->assertCreated();
    $latestId = $this->postJson('/currency-exchanges', exchangePayload([
        'source_currency' => 'USD', 'source_amount' => '2.00',
        'target_currency' => 'ARS', 'target_amount' => '3200.00',
    ]))->assertCreated()->json('id');

    $this->getJson('/currency-exchanges/latest?source_currency=ARS&target_currency=USD')
        ->assertSuccessful()->assertJsonPath('expense.currency', 'USD')->assertJsonPath('income.amount', '3200.00');
    $this->putJson("/currency-exchanges/{$latestId}", exchangePayload([
        'source_currency' => 'USD', 'source_amount' => '2.00',
        'target_currency' => 'ARS', 'target_amount' => '3400.00',
    ]))->assertSuccessful();
    $this->getJson('/currency-exchanges/latest?source_currency=USD&target_currency=ARS')
        ->assertSuccessful()->assertJsonPath('income.amount', '3400.00');
});

test('a failed second leg rolls back the exchange and expense', function () {
    $user = User::factory()->create();
    Transaction::creating(function (Transaction $transaction): void {
        if ($transaction->type === 'income' && $transaction->exchange_id !== null) {
            throw new RuntimeException('Failed income leg');
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->postJson('/currency-exchanges', exchangePayload()))->toThrow(RuntimeException::class);
    expect(CurrencyExchange::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

test('versioned API and idempotent web retries preserve a single pair', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $first = $this->withHeader('Idempotency-Key', 'currency-exchange-1')
        ->postJson('/currency-exchanges', exchangePayload())->assertCreated();
    $this->withHeader('Idempotency-Key', 'currency-exchange-1')
        ->postJson('/currency-exchanges', exchangePayload())->assertCreated()->assertJsonPath('id', $first->json('id'));

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/currency-exchanges', exchangePayload(['source_amount' => '2000.00']))
        ->assertCreated()->assertJsonPath('data.expense.amount', '2000.00');
    $transactions = $this->getJson('/api/v1/transactions?period=2026-09')
        ->assertSuccessful()->assertJsonCount(4, 'data')->json('data');
    expect(collect($transactions)->pluck('exchange_id')->unique()->sort()->values()->all())->toBe([1, 2]);
    expect(CurrencyExchange::query()->count())->toBe(2)
        ->and(Transaction::query()->count())->toBe(4);
});
