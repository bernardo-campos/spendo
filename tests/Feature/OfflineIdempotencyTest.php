<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a repeated offline create returns the original response without duplicating the resource', function () {
    $user = User::factory()->create();
    $payload = ['name' => 'Alimentos', 'slug' => 'alimentos', 'scope' => 'expense'];

    $first = $this->actingAs($user)
        ->withHeader('Idempotency-Key', 'offline-category-create-1')
        ->postJson('/categories', $payload)
        ->assertCreated();

    $second = $this->actingAs($user)
        ->withHeader('Idempotency-Key', 'offline-category-create-1')
        ->postJson('/categories', $payload)
        ->assertCreated();

    expect(Category::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($second->json('id'))->toBe($first->json('id'));
});

test('an idempotency key cannot be reused for a different request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('Idempotency-Key', 'offline-category-create-2')
        ->postJson('/categories', ['name' => 'Hogar', 'slug' => 'hogar', 'scope' => 'expense'])
        ->assertCreated();

    $this->actingAs($user)
        ->withHeader('Idempotency-Key', 'offline-category-create-2')
        ->postJson('/categories', ['name' => 'Viajes', 'slug' => 'viajes', 'scope' => 'expense'])
        ->assertUnprocessable();

    expect(Category::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('a repeated offline delete replays the original no-content response', function () {
    $user = User::factory()->create();
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Movilidad',
        'slug' => 'movilidad',
        'scope' => 'expense',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withHeader('Idempotency-Key', 'offline-category-delete-1')
        ->deleteJson("/categories/{$category->id}")
        ->assertNoContent();

    $this->actingAs($user)
        ->withHeader('Idempotency-Key', 'offline-category-delete-1')
        ->deleteJson("/categories/{$category->id}")
        ->assertNoContent();

    expect(Category::query()->find($category->id))->toBeNull();
});

test('idempotency keys are isolated by authenticated user', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $payload = ['name' => 'Servicios', 'slug' => 'servicios', 'scope' => 'expense'];

    $this->actingAs($firstUser)
        ->withHeader('Idempotency-Key', 'offline-shared-key')
        ->postJson('/categories', $payload)
        ->assertCreated();

    $this->actingAs($secondUser)
        ->withHeader('Idempotency-Key', 'offline-shared-key')
        ->postJson('/categories', $payload)
        ->assertCreated();

    expect(Category::query()->count())->toBe(2);
});
