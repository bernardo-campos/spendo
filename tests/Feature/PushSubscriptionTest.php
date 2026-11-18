<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\VAPID;

uses(RefreshDatabase::class);

beforeEach(function () {
    $keys = VAPID::createVapidKeys();
    config([
        'webpush.vapid.subject' => 'mailto:push@spendo.test',
        'webpush.vapid.public_key' => $keys['publicKey'],
        'webpush.vapid.private_key' => $keys['privateKey'],
    ]);
    $this->subscription = [
        'endpoint' => 'https://web.push.apple.com/test-device',
        'keys' => [
            'p256dh' => $keys['publicKey'],
            'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ],
    ];
});

test('push endpoints require a verified session', function () {
    $this->getJson('/push-subscriptions')->assertUnauthorized();
    $this->postJson('/push-subscriptions', $this->subscription)->assertUnauthorized();
    $this->deleteJson('/push-subscriptions', ['endpoint' => $this->subscription['endpoint']])->assertUnauthorized();

    $this->actingAs(User::factory()->unverified()->create());
    $this->getJson('/push-subscriptions')->assertForbidden();
    $this->postJson('/push-subscriptions', $this->subscription)->assertForbidden();
    $this->deleteJson('/push-subscriptions', ['endpoint' => $this->subscription['endpoint']])->assertForbidden();
});

test('configuration only exposes the public key and is not cached', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/push-subscriptions');

    $response->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['configured' => true, 'public_key' => config('webpush.vapid.public_key')]);

    expect($response->getContent())->not->toContain(config('webpush.vapid.private_key'));
});

test('missing configuration disables push without affecting the rest of the application', function () {
    config(['webpush.vapid.private_key' => null]);
    $this->actingAs(User::factory()->create());
    $this->getJson('/push-subscriptions')->assertExactJson(['configured' => false, 'public_key' => null]);
    $this->postJson('/push-subscriptions', $this->subscription)->assertServiceUnavailable();
    $this->assertDatabaseCount('push_subscriptions', 0);
});

test('a device subscription is saved once and its keys can be refreshed', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/push-subscriptions', $this->subscription)->assertNoContent();
    $this->postJson('/push-subscriptions', $this->subscription)->assertNoContent();
    $this->assertDatabaseCount('push_subscriptions', 1);

    $this->subscription['keys']['auth'] = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    $this->postJson('/push-subscriptions', $this->subscription)->assertNoContent();

    expect($user->pushSubscriptions()->sole()->auth_token)->toBe($this->subscription['keys']['auth']);
});

test('a user can enable multiple devices and remove only the current one', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/push-subscriptions', $this->subscription)->assertNoContent();
    $otherDevice = [...$this->subscription, 'endpoint' => 'https://web.push.apple.com/another-device'];
    $this->postJson('/push-subscriptions', $otherDevice)->assertNoContent();
    $this->deleteJson('/push-subscriptions', ['endpoint' => $this->subscription['endpoint']])->assertNoContent();
    $this->deleteJson('/push-subscriptions', ['endpoint' => $this->subscription['endpoint']])->assertNoContent();

    expect($user->pushSubscriptions()->sole()->endpoint)->toBe($otherDevice['endpoint']);
});

test('a user cannot overwrite or delete another accounts subscription', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/push-subscriptions', $this->subscription)->assertNoContent();
    $this->actingAs(User::factory()->create());
    $this->postJson('/push-subscriptions', $this->subscription)->assertConflict();
    $this->deleteJson('/push-subscriptions', ['endpoint' => $this->subscription['endpoint']])->assertNoContent();

    expect($owner->pushSubscriptions()->count())->toBe(1);
});

test('untrusted push destinations cannot be stored', function (string $endpoint) {
    $this->subscription['endpoint'] = $endpoint;
    $this->actingAs(User::factory()->create())->postJson('/push-subscriptions', $this->subscription)
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    $this->assertDatabaseCount('push_subscriptions', 0);
})->with([
    'http://web.push.apple.com/device',
    'https://127.0.0.1/private',
    'https://attacker.test/device',
    'https://web.push.apple.com.attacker.test/device',
    'https://web.push.apple.com:8080/device',
    'https://user:pass@web.push.apple.com/device',
    'https://web.push.apple.com/device#fragment',
]);

test('subscription keys must be correctly shaped and cannot be replaced with user supplied ownership', function () {
    $this->subscription['keys'] = ['p256dh' => 'invalid', 'auth' => 'invalid'];
    $this->subscription['user_id'] = User::factory()->create()->id;
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/push-subscriptions', $this->subscription)
        ->assertUnprocessable()->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    $this->assertDatabaseCount('push_subscriptions', 0);
});
