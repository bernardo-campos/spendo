<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function expenseListDisplayPreferences(array $overrides = []): array
{
    return [
        'show_category' => true,
        'show_description' => true,
        'show_cash_payment_method' => false,
        'show_credit_payment_method' => true,
        'show_tags' => true,
        'show_notes' => false,
        ...$overrides,
    ];
}

test('it returns default expense list display preferences when none were saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/visualization-preferences')
        ->assertSuccessful()
        ->assertJsonPath('expense_list', expenseListDisplayPreferences());
});

test('it saves expense list display preferences for the authenticated user', function () {
    $user = User::factory()->create();
    $preferences = expenseListDisplayPreferences([
        'show_category' => false,
        'show_cash_payment_method' => true,
        'show_credit_payment_method' => false,
        'show_notes' => true,
    ]);

    $this->actingAs($user)
        ->putJson('/visualization-preferences', ['expense_list' => $preferences])
        ->assertSuccessful()
        ->assertJsonPath('expense_list', $preferences);

    expect($user->refresh()->expense_list_display_preferences)->toBe($preferences);

    $this->actingAs($user)
        ->getJson('/visualization-preferences')
        ->assertSuccessful()
        ->assertJsonPath('expense_list', $preferences);
});

test('it keeps expense list display preferences isolated by user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/visualization-preferences', [
            'expense_list' => expenseListDisplayPreferences(['show_notes' => true]),
        ])
        ->assertSuccessful();

    $this->actingAs($otherUser)
        ->getJson('/visualization-preferences')
        ->assertSuccessful()
        ->assertJsonPath('expense_list', expenseListDisplayPreferences());
});

test('it rejects invalid expense list display preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/visualization-preferences', [
            'expense_list' => expenseListDisplayPreferences(['show_tags' => 'yes']),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expense_list.show_tags']);
});

test('it requires the category or description to remain visible', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/visualization-preferences', [
            'expense_list' => expenseListDisplayPreferences([
                'show_category' => false,
                'show_description' => false,
            ]),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expense_list']);
});
