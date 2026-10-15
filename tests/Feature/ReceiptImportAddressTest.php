<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an authenticated user can generate and view a private receipt address', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/receipt-import-address')
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('address', null);

    expect($user->refresh()->receipt_import_token)->toBeNull();

    $address = $this->actingAs($user)
        ->postJson('/receipt-import-address')
        ->assertSuccessful()
        ->json('address');

    expect($address)->toMatch('/^importar\+[a-f0-9]{40}@recibos\.spendo\.bernu\.dev$/');

    $this->actingAs($user)
        ->getJson('/receipt-import-address')
        ->assertSuccessful()
        ->assertJsonPath('address', $address);

    $this->actingAs($user)
        ->postJson('/receipt-import-address')
        ->assertSuccessful()
        ->assertJsonPath('address', $address);
});

test('regenerating the address invalidates the old recipient', function () {
    $user = User::factory()->create();
    $oldAddress = $this->actingAs($user)->postJson('/receipt-import-address')->json('address');

    $newAddress = $this->actingAs($user)
        ->postJson('/receipt-import-address/rotate')
        ->assertSuccessful()
        ->json('address');

    expect($newAddress)->not->toBe($oldAddress);

    $this->actingAs($user)
        ->getJson('/receipt-import-address')
        ->assertJsonPath('address', $newAddress);

    $this->artisan('receipts:ingest', ['recipient' => $oldAddress])->assertExitCode(67);
});

test('a user cannot view or regenerate another users receipt address', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownerAddress = $this->actingAs($owner)->postJson('/receipt-import-address')->json('address');

    $this->actingAs($otherUser)
        ->getJson('/receipt-import-address')
        ->assertJsonPath('address', null);

    $this->actingAs($otherUser)->postJson('/receipt-import-address/rotate')->assertSuccessful();

    $this->actingAs($owner)
        ->getJson('/receipt-import-address')
        ->assertJsonPath('address', $ownerAddress);
});

test('receipt address endpoints require a verified session', function () {
    $this->getJson('/receipt-import-address')->assertUnauthorized();
    $this->postJson('/receipt-import-address')->assertUnauthorized();
    $this->postJson('/receipt-import-address/rotate')->assertUnauthorized();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/receipt-import-address')->assertRedirect('/email/verify');
    $this->actingAs($user)->post('/receipt-import-address')->assertRedirect('/email/verify');
});

test('a user can choose an expense category for Uber receipts', function () {
    $user = User::factory()->create();
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Transporte',
        'slug' => 'transporte',
        'scope' => 'expense',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson('/receipt-import-settings')
        ->assertSuccessful()
        ->assertJsonPath('providers.0.key', 'uber')
        ->assertJsonPath('providers.0.category_id', null)
        ->assertJsonPath('providers.0.place', 'Uber');

    $this->actingAs($user)
        ->putJson('/receipt-import-settings/uber', ['category_id' => $category->id, 'place' => '  Viajes en auto  '])
        ->assertSuccessful()
        ->assertJsonPath('category_id', $category->id)
        ->assertJsonPath('place', 'Viajes en auto');

    $this->actingAs($user)
        ->getJson('/receipt-import-settings')
        ->assertJsonPath('providers.0.category_id', $category->id)
        ->assertJsonPath('providers.0.place', 'Viajes en auto');

    $this->actingAs($user)
        ->putJson('/receipt-import-settings/uber', ['category_id' => $category->id])
        ->assertJsonPath('place', 'Viajes en auto');

    $this->actingAs($user)
        ->putJson('/receipt-import-settings/uber', ['category_id' => null, 'place' => ''])
        ->assertSuccessful()
        ->assertJsonPath('category_id', null)
        ->assertJsonPath('place', 'Uber');
});

test('receipt category settings reject unsupported providers and invalid categories', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCategory = Category::query()->create([
        'user_id' => $otherUser->id, 'name' => 'Viajes', 'slug' => 'viajes',
        'scope' => 'expense', 'is_active' => true,
    ]);
    $incomeCategory = Category::query()->create([
        'user_id' => $user->id, 'name' => 'Salario', 'slug' => 'salario',
        'scope' => 'income', 'is_active' => true,
    ]);
    $inactiveCategory = Category::query()->create([
        'user_id' => $user->id, 'name' => 'Vieja', 'slug' => 'vieja',
        'scope' => 'expense', 'is_active' => false,
    ]);

    $this->actingAs($user)->putJson('/receipt-import-settings/didi', ['category_id' => null])->assertNotFound();

    foreach ([$otherCategory, $incomeCategory, $inactiveCategory] as $category) {
        $this->actingAs($user)
            ->putJson('/receipt-import-settings/uber', ['category_id' => $category->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    $this->actingAs($user)
        ->putJson('/receipt-import-settings/uber', ['category_id' => null, 'place' => str_repeat('a', 121)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('place');

});

test('receipt settings require a verified session', function () {
    $this->getJson('/receipt-import-settings')->assertUnauthorized();
    $this->putJson('/receipt-import-settings/uber', ['category_id' => null])->assertUnauthorized();

    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get('/receipt-import-settings')->assertRedirect('/email/verify');
});
