<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated app screen routes serve the application shell', function (string $path) {
    $response = $this->actingAs(User::factory()->create())->get($path);

    $response->assertSuccessful()->assertViewIs('app');
})->with([
    '/app',
    '/app/incomes',
    '/app/expenses',
    '/app/categories',
    '/app/tags',
    '/app/cards',
    '/app/transactions/create?type=expense',
    '/app/transactions/123/edit?type=expense',
]);
