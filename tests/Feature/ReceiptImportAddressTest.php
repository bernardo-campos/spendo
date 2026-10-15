<?php

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
