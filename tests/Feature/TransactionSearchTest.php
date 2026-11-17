<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

function createSearchTransaction(User $user, array $attributes = []): Transaction
{
    return Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'expense',
        'description' => 'Compra semanal',
        'place' => 'Mercado Central',
        'notes' => 'Con descuento',
        'amount' => 100,
        'currency' => 'ARS',
        'payment_method' => 'cash',
        'purchase_date' => '2026-11-16',
        'payment_date' => '2026-11-16',
        ...$attributes,
    ]);
}

test('search spans all requested months and includes both date boundaries', function () {
    $start = createSearchTransaction($this->user, ['purchase_date' => '2025-12-15']);
    $middle = createSearchTransaction($this->user, ['purchase_date' => '2026-06-16']);
    $end = createSearchTransaction($this->user, ['purchase_date' => '2026-11-30']);
    createSearchTransaction($this->user, ['purchase_date' => '2025-12-14']);
    createSearchTransaction($this->user, ['purchase_date' => '2026-12-01']);
    createSearchTransaction($this->user, ['type' => 'income']);
    createSearchTransaction(User::factory()->create());

    $this->actingAs($this->user)->getJson('/transaction-search?'.http_build_query([
        'type' => 'expense', 'date_scope' => 'range', 'date_from' => '2025-12-15', 'date_to' => '2026-11-30',
    ]))->assertSuccessful()->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.id', $end->id)
        ->assertJsonPath('data.1.id', $middle->id)
        ->assertJsonPath('data.2.id', $start->id);
});

test('search supports a specific period or all records independently of the header period', function () {
    $older = createSearchTransaction($this->user, ['purchase_date' => '2020-01-01']);
    $recent = createSearchTransaction($this->user);
    createSearchTransaction($this->user, ['type' => 'income']);
    createSearchTransaction(User::factory()->create());

    $this->actingAs($this->user)->getJson('/transaction-search?type=expense&date_scope=period&period=2020-01')
        ->assertSuccessful()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $older->id);
    $this->getJson('/transaction-search?type=expense&date_scope=all&period=2026-11&date_from=2026-11-01')
        ->assertSuccessful()->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $recent->id)->assertJsonPath('data.1.id', $older->id);
});

test('search selects income without expense payment filters', function () {
    createSearchTransaction($this->user);
    $income = createSearchTransaction($this->user, ['type' => 'income', 'payment_method' => null]);

    $this->actingAs($this->user)->getJson('/transaction-search?type=income&date_scope=all&payment_method=credit')
        ->assertSuccessful()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $income->id);
});

test('period search includes the first and last day of the selected month', function () {
    createSearchTransaction($this->user, ['purchase_date' => '2026-02-01']);
    createSearchTransaction($this->user, ['purchase_date' => '2026-02-28']);
    createSearchTransaction($this->user, ['purchase_date' => '2026-03-01']);

    $this->actingAs($this->user)->getJson('/transaction-search?type=expense&date_scope=period&period=2026-02')
        ->assertSuccessful()->assertJsonPath('meta.total', 2);
});

test('search combines independent text, amount, category, tag, currency and payment filters', function () {
    $category = Category::query()->create(['user_id' => $this->user->id, 'name' => 'Comida', 'slug' => 'comida', 'scope' => 'expense']);
    $tag = Tag::query()->create(['user_id' => $this->user->id, 'name' => 'Hogar', 'slug' => 'hogar']);
    $otherTag = Tag::query()->create(['user_id' => $this->user->id, 'name' => 'Trabajo', 'slug' => 'trabajo']);
    $card = Card::query()->create(['user_id' => $this->user->id, 'name' => 'Visa', 'closing_day' => 10, 'due_day' => 20, 'last_four_digits' => '1234']);
    $attributes = ['category_id' => $category->id, 'card_id' => $card->id, 'payment_method' => 'credit'];
    $match = createSearchTransaction($this->user, $attributes);
    $match->tags()->attach($tag);
    foreach ([['description' => 'Otra cosa'], ['place' => 'Otro lugar'], ['notes' => 'Otra nota'], ['amount' => 99], ['amount' => 201], ['category_id' => null], ['currency' => 'USD'], ['payment_method' => 'cash'], ['card_id' => null]] as $difference) {
        $excluded = createSearchTransaction($this->user, [...$attributes, ...$difference]);
        $excluded->tags()->attach($tag);
    }
    createSearchTransaction($this->user, $attributes)->tags()->attach($otherTag);

    $this->actingAs($this->user)->getJson('/transaction-search?'.http_build_query([
        'type' => 'expense', 'date_scope' => 'all', 'description' => 'semanal', 'place' => 'Central', 'notes' => 'descuento',
        'amount_min' => 100, 'amount_max' => 200, 'category_id' => $category->id, 'tag_ids' => [$tag->id],
        'currency' => 'ARS', 'payment_method' => 'credit', 'card_id' => $card->id,
    ]))->assertSuccessful()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.tags.0.id', $tag->id)->assertJsonPath('data.0.category.id', $category->id);
});

test('amount bounds may be used individually and are inclusive', function (array $bounds, int $expected) {
    foreach ([0.5, 100, 200] as $amount) {
        createSearchTransaction($this->user, ['amount' => $amount]);
    }
    $this->actingAs($this->user)->getJson('/transaction-search?'.http_build_query([
        'type' => 'expense', 'date_scope' => 'all', ...$bounds,
    ]))->assertSuccessful()->assertJsonPath('meta.total', $expected);
})->with([
    'minimum only' => [['amount_min' => 100], 2],
    'maximum only' => [['amount_max' => 100], 2],
    'equal bounds' => [['amount_min' => 100, 'amount_max' => 100], 1],
    'zero minimum' => [['amount_min' => 0], 3],
]);

test('search validates invalid filters', function (array $filters, array $errors) {
    $this->actingAs($this->user)->getJson('/transaction-search?'.http_build_query([
        'type' => 'expense', 'date_scope' => 'all', ...$filters,
    ]))->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    'invalid type' => [['type' => 'other'], ['type']],
    'invalid scope' => [['date_scope' => 'other'], ['date_scope']],
    'missing period' => [['date_scope' => 'period'], ['period']],
    'invalid period' => [['date_scope' => 'period', 'period' => '2026-13'], ['period']],
    'missing dates' => [['date_scope' => 'range'], ['date_from', 'date_to']],
    'reversed dates' => [['date_scope' => 'range', 'date_from' => '2026-12-01', 'date_to' => '2026-11-01'], ['date_to']],
    'impossible date' => [['date_scope' => 'range', 'date_from' => '2026-02-30', 'date_to' => '2026-03-01'], ['date_from']],
    'reversed amounts' => [['amount_min' => 200, 'amount_max' => 100], ['amount_max']],
    'negative amount' => [['amount_min' => -1], ['amount_min']],
    'invalid page' => [['page' => 0], ['page']],
]);

test('search rejects other users category, tags and card filters', function () {
    $other = User::factory()->create();
    $category = Category::query()->create(['user_id' => $other->id, 'name' => 'Privada', 'slug' => 'privada', 'scope' => 'expense']);
    $tag = Tag::query()->create(['user_id' => $other->id, 'name' => 'Privada', 'slug' => 'privada']);
    $card = Card::query()->create(['user_id' => $other->id, 'name' => 'Visa', 'closing_day' => 10, 'due_day' => 20, 'last_four_digits' => '1234']);

    $this->actingAs($this->user)->getJson('/transaction-search?'.http_build_query([
        'type' => 'expense', 'date_scope' => 'all', 'category_id' => $category->id, 'tag_ids' => [$tag->id], 'card_id' => $card->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'tag_ids.0', 'card_id']);
});

test('search paginates all matching records without a month restriction', function () {
    $ids = [];
    for ($i = 0; $i < 27; $i++) {
        $ids[] = createSearchTransaction($this->user, ['purchase_date' => $i % 2 ? '2020-01-01' : '2026-11-16'])->id;
    }
    $first = $this->actingAs($this->user)->getJson('/transaction-search?type=expense&date_scope=all')
        ->assertSuccessful()->assertJsonPath('meta.total', 27)->assertJsonPath('meta.last_page', 2)->assertJsonCount(25, 'data');
    $second = $this->getJson('/transaction-search?type=expense&date_scope=all&page=2')
        ->assertSuccessful()->assertJsonCount(2, 'data');
    expect(collect([...$first->json('data'), ...$second->json('data')])->pluck('id')->sort()->values()->all())
        ->toBe($ids);
});

test('search requires an authenticated verified user', function () {
    $this->getJson('/transaction-search?type=expense&date_scope=all')->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())
        ->getJson('/transaction-search?type=expense&date_scope=all')->assertForbidden();
});

test('search totals include every matching page by currency and respect all filters', function (string $type) {
    for ($i = 0; $i < 27; $i++) {
        createSearchTransaction($this->user, ['type' => $type, 'amount' => 10.5, 'purchase_date' => '2026-03-01']);
    }
    createSearchTransaction($this->user, ['type' => $type, 'amount' => 20.25, 'currency' => 'USD', 'purchase_date' => '2026-03-31']);
    createSearchTransaction($this->user, ['type' => $type, 'amount' => 999, 'description' => 'No coincide', 'purchase_date' => '2026-03-01']);
    createSearchTransaction($this->user, ['type' => $type, 'amount' => 999, 'purchase_date' => '2025-01-01']);
    createSearchTransaction($this->user, ['type' => $type === 'income' ? 'expense' : 'income', 'amount' => 999, 'purchase_date' => '2026-03-01']);
    createSearchTransaction(User::factory()->create(), ['type' => $type, 'amount' => 999, 'purchase_date' => '2026-03-01']);

    $url = '/transaction-search?'.http_build_query([
        'type' => $type, 'date_scope' => 'period', 'period' => '2026-03', 'description' => 'semanal',
    ]);
    foreach ([1, 2] as $page) {
        $response = $this->actingAs($this->user)->getJson($url.'&page='.$page)
            ->assertSuccessful()->assertJsonPath('meta.total', 28);
        expect((float) $response->json('totals.ARS'))->toBe(283.5)
            ->and((float) $response->json('totals.USD'))->toBe(20.25);
    }
    $response = $this->getJson($url.'&currency=USD')->assertSuccessful();
    expect((float) $response->json('totals.ARS'))->toBe(0.0)
        ->and((float) $response->json('totals.USD'))->toBe(20.25);
    $response = $this->getJson($url.'&amount_min=5000')->assertSuccessful()->assertJsonPath('meta.total', 0);
    expect((float) $response->json('totals.ARS'))->toBe(0.0)
        ->and((float) $response->json('totals.USD'))->toBe(0.0);
})->with(['income', 'expense']);
