<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\RecurringExpense;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('the expense form fields create a fixed recurring card charge', function () {
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id,
        'name' => 'Visa',
        'last_four_digits' => '1234',
        'closing_day' => 10,
        'due_day' => 20,
    ]);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Suscripciones',
        'slug' => 'suscripciones',
        'scope' => 'expense',
    ]);

    $this->actingAs($user)->postJson('/recurring-expenses', [
        'description' => 'Disney+',
        'place' => 'Disney',
        'category_id' => $category->id,
        'payment_method' => 'credit',
        'card_id' => $card->id,
        'currency' => 'ARS',
        'amount_type' => 'fixed',
        'amount' => 15900,
        'day_of_month' => 15,
        'starts_on' => '2026-10-01',
        'ends_on' => null,
        'is_active' => true,
    ])->assertCreated()
        ->assertJsonPath('description', 'Disney+')
        ->assertJsonPath('card_id', $card->id)
        ->assertJsonPath('category_id', $category->id)
        ->assertJsonPath('amount', '15900.00');
});

test('monthly previews do not persist future transactions or occurrences', function () {
    $user = User::factory()->create();
    RecurringExpense::factory()->for($user)->create(['day_of_month' => 31, 'starts_on' => '2026-01-01']);

    $this->actingAs($user)->getJson('/recurring-expenses/preview?period=2026-02')
        ->assertSuccessful()
        ->assertJsonPath('0.charge_date', '2026-02-28')
        ->assertJsonPath('0.status', 'pending');

    expect(Transaction::query()->count())->toBe(0)
        ->and($user->recurringExpenses()->first()->occurrences()->count())->toBe(0);
});

test('a variable expense requires an amount and can only be confirmed once', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'amount_type' => 'variable', 'amount' => null, 'starts_on' => '2026-09-01',
    ]);

    $this->actingAs($user)->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed',
    ])->assertUnprocessable();

    $this->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 15359,
    ])->assertCreated();

    $this->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 16000,
    ])->assertConflict();

    $this->getJson('/recurring-expenses/preview?period=2026-11')
        ->assertSuccessful()
        ->assertJsonPath('0.suggested_amount', '15359.00');

    expect(Transaction::query()->count())->toBe(1);
});

test('confirmation preserves a credit card charge and calculates its payment month', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id, 'name' => 'Visa', 'last_four_digits' => '1234',
        'closing_day' => 10, 'due_day' => 20, 'is_active' => true,
    ]);
    $expense = RecurringExpense::factory()->for($user)->create([
        'payment_method' => 'credit', 'card_id' => $card->id,
        'currency' => 'USD', 'amount' => 20, 'day_of_month' => 15,
        'starts_on' => '2026-10-01',
    ]);

    $this->actingAs($user)->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed',
    ])->assertCreated();

    $transaction = Transaction::query()->sole();
    expect($transaction->amount)->toBe('20.00')
        ->and($transaction->currency->value)->toBe('USD')
        ->and($transaction->purchase_date->toDateString())->toBe('2026-10-15')
        ->and($transaction->payment_date->toDateString())->toBe('2026-12-20');
});

test('changing a rule from a later month preserves earlier confirmed amounts', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'amount' => 15900, 'starts_on' => '2026-09-01',
    ]);
    $this->actingAs($user)->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed',
    ])->assertCreated();

    $this->putJson("/recurring-expenses/{$expense->id}", [
        'description' => $expense->description,
        'payment_method' => 'cash', 'currency' => 'ARS',
        'amount_type' => 'fixed', 'amount' => 18000,
        'day_of_month' => 10, 'starts_on' => '2026-09-01',
        'effective_period' => '2026-11',
    ])->assertSuccessful();

    $this->getJson('/recurring-expenses/preview?period=2026-11')
        ->assertJsonPath('0.suggested_amount', '18000.00');

    expect(Transaction::query()->sole()->amount)->toBe('15900.00')
        ->and($expense->fresh()->ends_on->toDateString())->toBe('2026-10-31');
});

test('users cannot decide another users recurrence', function () {
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->create();

    $this->actingAs($user)->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'skipped',
    ])->assertNotFound();
});

test('a user can create a variable rule and skip one month without creating a transaction', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();

    $created = $this->actingAs($user)->postJson('/recurring-expenses', [
        'description' => 'Obra social',
        'payment_method' => 'cash',
        'currency' => 'ARS',
        'amount_type' => 'variable',
        'day_of_month' => 10,
        'starts_on' => '2026-09-01',
    ])->assertCreated();

    $id = $created->json('id');
    $this->postJson("/recurring-expenses/{$id}/decisions", [
        'period' => '2026-10', 'status' => 'skipped',
    ])->assertCreated();

    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.status', 'skipped');

    expect(Transaction::query()->count())->toBe(0);
});

test('a variable amount suggestion survives a rule revision', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'amount_type' => 'variable', 'amount' => null, 'starts_on' => '2026-09-01',
    ]);

    $this->actingAs($user)->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 15359,
    ])->assertCreated();
    $this->putJson("/recurring-expenses/{$expense->id}", [
        'description' => 'Obra social nueva',
        'payment_method' => 'cash', 'currency' => 'ARS',
        'amount_type' => 'variable', 'day_of_month' => 10,
        'starts_on' => '2026-09-01', 'effective_period' => '2026-11',
    ])->assertSuccessful();

    $this->getJson('/recurring-expenses/preview?period=2026-11')
        ->assertJsonPath('0.suggested_amount', '15359.00');
});

test('a future charge cannot be confirmed early', function () {
    $this->travelTo(Carbon::parse('2026-10-08'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'day_of_month' => 15, 'starts_on' => '2026-10-01',
    ]);

    $this->actingAs($user)->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed',
    ])->assertUnprocessable();

    expect(Transaction::query()->count())->toBe(0);
});

test('versioned API exposes recurring rules as resources', function () {
    $user = User::factory()->create();
    RecurringExpense::factory()->for($user)->create(['description' => 'Disney+']);

    $this->actingAs($user)->getJson('/api/v1/recurring-expenses')
        ->assertSuccessful()
        ->assertJsonPath('data.0.description', 'Disney+');
});

test('deleting a confirmed transaction makes its month pending again', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create(['starts_on' => '2026-10-01']);

    $this->actingAs($user)->postJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed',
    ])->assertCreated();
    $transaction = Transaction::query()->sole();
    $this->deleteJson("/transactions/{$transaction->id}")->assertNoContent();

    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.status', 'pending');
});

test('a fixed recurrence is visible without creating transactions and a single month can be edited or removed', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'description' => 'Disney+', 'amount' => 15900, 'day_of_month' => 15,
        'starts_on' => '2026-09-01',
    ]);

    $this->actingAs($user)->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.status', 'pending')
        ->assertJsonPath('0.amount', '15900.00');
    expect(Transaction::query()->count())->toBe(0);

    $this->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 17000,
        'description' => 'Disney+ extra',
    ])->assertSuccessful()->assertJsonPath('status', 'confirmed');
    expect(Transaction::query()->sole()->amount)->toBe('17000.00');
    expect($expense->occurrences()->sole()->transaction_id)->toBe(Transaction::query()->sole()->id);
    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.status', 'confirmed')
        ->assertJsonPath('0.amount', '17000.00')
        ->assertJsonPath('0.description', 'Disney+ extra');

    $this->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 18000,
    ])->assertSuccessful();
    expect(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->sole()->amount)->toBe('18000.00');

    $this->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'skipped',
    ])->assertSuccessful();
    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.status', 'skipped');
    $this->getJson('/recurring-expenses/preview?period=2026-11')
        ->assertJsonPath('0.amount', '15900.00');
    expect(Transaction::query()->count())->toBe(0);
});

test('a variable recurrence uses the previous amount as an estimate until edited', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'amount_type' => 'variable', 'amount' => null, 'starts_on' => '2026-09-01',
    ]);

    $this->actingAs($user)->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-09', 'status' => 'confirmed', 'amount' => 15359,
    ])->assertSuccessful();
    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.amount', '15359.00')
        ->assertJsonPath('0.status', 'pending');

    $this->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 16000,
    ])->assertSuccessful();
    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.amount', '16000.00')
        ->assertJsonPath('0.status', 'confirmed');
    expect(Transaction::query()->count())->toBe(2);
});

test('a confirmed recurring card charge stays in its charge month preview and is not duplicated in the payment month list', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $card = Card::query()->create([
        'user_id' => $user->id, 'name' => 'Visa', 'last_four_digits' => '1234',
        'closing_day' => 10, 'due_day' => 20, 'is_active' => true,
    ]);
    $expense = RecurringExpense::factory()->for($user)->create([
        'payment_method' => 'credit', 'card_id' => $card->id,
        'amount' => 20, 'day_of_month' => 15, 'starts_on' => '2026-10-01',
    ]);

    $this->actingAs($user)->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 25,
    ])->assertSuccessful();

    $this->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.amount', '25.00')
        ->assertJsonPath('0.status', 'confirmed');
    $this->getJson('/transactions?period=2026-12')->assertExactJson([]);
    expect(Transaction::query()->sole()->payment_date->toDateString())->toBe('2026-12-20');
});

test('a bounded recurring expense previews and saves numbered notes without creating future transactions', function () {
    $this->travelTo(Carbon::parse('2026-10-31'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'day_of_month' => 31,
        'starts_on' => '2026-01-31',
        'ends_on' => '2026-10-31',
        'notes' => 'Suscripción anual',
        'number_occurrences_in_notes' => true,
    ]);

    $this->actingAs($user)->getJson('/recurring-expenses/preview?period=2026-02')
        ->assertJsonPath('0.charge_date', '2026-02-28')
        ->assertJsonPath('0.notes', "Suscripción anual\n2 de 10");

    expect(Transaction::query()->count())->toBe(0);

    $this->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 1000,
    ])->assertSuccessful();

    expect(Transaction::query()->sole()->notes)->toBe("Suscripción anual\n10 de 10");
});

test('the recurring expense form stores its note and numbering choice', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/recurring-expenses', [
        'description' => 'Curso', 'payment_method' => 'cash', 'currency' => 'ARS',
        'amount_type' => 'fixed', 'amount' => 1000, 'day_of_month' => 15,
        'starts_on' => '2026-01-01', 'ends_on' => '2026-10-31',
        'notes' => 'Módulo mensual', 'number_occurrences_in_notes' => true,
    ])->assertCreated()
        ->assertJsonPath('notes', 'Módulo mensual')
        ->assertJsonPath('number_occurrences_in_notes', true);

    $this->getJson('/api/v1/recurring-expenses')
        ->assertJsonPath('data.0.id', $response->json('id'))
        ->assertJsonPath('data.0.number_occurrences_in_notes', true);
});

test('numbering counts only charge dates inside the selected range and requires an end date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/recurring-expenses', [
        'description' => 'Curso', 'payment_method' => 'cash', 'currency' => 'ARS',
        'amount_type' => 'fixed', 'amount' => 1000, 'day_of_month' => 15,
        'starts_on' => '2026-01-20', 'number_occurrences_in_notes' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('ends_on');

    $this->postJson('/recurring-expenses', [
        'description' => 'Curso', 'payment_method' => 'cash', 'currency' => 'ARS',
        'amount_type' => 'fixed', 'amount' => 1000, 'day_of_month' => 15,
        'starts_on' => '2026-01-20', 'ends_on' => '2026-02-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('ends_on');

    $expense = RecurringExpense::factory()->for($user)->create([
        'day_of_month' => 15, 'starts_on' => '2026-01-20',
        'ends_on' => '2026-03-15', 'number_occurrences_in_notes' => true,
    ]);

    $this->getJson('/recurring-expenses/preview?period=2026-02')
        ->assertJsonPath('0.notes', '1 de 2');
    $this->getJson('/recurring-expenses/preview?period=2026-03')
        ->assertJsonPath('0.notes', '2 de 2');
});

test('numbering is optional and an ordinary recurring note remains unchanged', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31',
        'notes' => 'Pago mensual', 'number_occurrences_in_notes' => false,
    ]);

    $this->actingAs($user)->getJson('/recurring-expenses/preview?period=2026-10')
        ->assertJsonPath('0.notes', 'Pago mensual');
    $this->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 1000,
    ])->assertSuccessful();

    expect(Transaction::query()->sole()->notes)->toBe('Pago mensual');
});

test('numbering continues across later revisions of a recurring rule', function () {
    $this->travelTo(Carbon::parse('2026-11-20'));
    $user = User::factory()->create();
    $expense = RecurringExpense::factory()->for($user)->create([
        'day_of_month' => 10, 'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31', 'number_occurrences_in_notes' => true,
    ]);

    $this->actingAs($user)->putJson("/recurring-expenses/{$expense->id}/decisions", [
        'period' => '2026-10', 'status' => 'confirmed', 'amount' => 1000,
    ])->assertSuccessful();

    $this->putJson("/recurring-expenses/{$expense->id}", [
        'description' => $expense->description,
        'payment_method' => 'cash', 'currency' => 'ARS',
        'amount_type' => 'fixed', 'amount' => 1200,
        'day_of_month' => 10, 'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31', 'effective_period' => '2026-11',
        'number_occurrences_in_notes' => true,
    ])->assertSuccessful();

    $this->getJson('/recurring-expenses/preview?period=2026-11')
        ->assertJsonPath('0.notes', '11 de 12');

    expect(Transaction::query()->sole()->notes)->toBe('10 de 12');
});
