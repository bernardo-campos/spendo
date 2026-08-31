<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function registrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Turnstile User',
        'email' => 'turnstile@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'cf-turnstile-response' => 'valid-turnstile-token',
    ], $overrides);
}

test('registration view renders the public Turnstile widget without exposing its secret', function () {
    config([
        'services.turnstile.site_key' => 'public-site-key',
        'services.turnstile.secret_key' => 'private-secret-key',
    ]);

    $this->get('/register')
        ->assertSuccessful()
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
        ->assertSee('data-sitekey="public-site-key"', false)
        ->assertSee('data-action="register"', false)
        ->assertSee('data-theme="auto"', false)
        ->assertDontSee('private-secret-key');
});

test('a valid Turnstile challenge allows registration', function () {
    Notification::fake();
    fakeSuccessfulTurnstile();

    $this->post('/register', registrationData())
        ->assertRedirect('/app');

    $user = User::query()->where('email', 'turnstile@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return $request->url() === config('services.turnstile.verify_url')
            && $data['secret'] === 'test-secret-key'
            && $data['response'] === 'valid-turnstile-token'
            && $data['remoteip'] === '127.0.0.1'
            && Str::isUuid($data['idempotency_key']);
    });
});

test('registration requires a Turnstile token', function () {
    fakeSuccessfulTurnstile();

    $this->post('/register', registrationData([
        'cf-turnstile-response' => null,
    ]))->assertSessionHasErrors([
        'cf-turnstile-response' => 'No pudimos verificar que seas una persona. Intentá nuevamente.',
    ]);

    $this->assertGuest();
    expect(User::query()->where('email', 'turnstile@example.com')->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('Cloudflare rejection blocks registration', function (string $errorCode) {
    config([
        'services.turnstile.secret_key' => 'test-secret-key',
        'services.turnstile.expected_hostname' => 'localhost',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => false,
            'error-codes' => [$errorCode],
        ]),
    ]);

    $this->post('/register', registrationData())
        ->assertSessionHasErrors('cf-turnstile-response')
        ->assertSessionHasInput('name', 'Turnstile User')
        ->assertSessionHasInput('email', 'turnstile@example.com');

    $this->assertGuest();
    expect(User::query()->where('email', 'turnstile@example.com')->exists())->toBeFalse();
})->with([
    'invalid token' => 'invalid-input-response',
    'expired or duplicate token' => 'timeout-or-duplicate',
]);

test('unexpected Turnstile context blocks registration', function (array $responseData) {
    config([
        'services.turnstile.secret_key' => 'test-secret-key',
        'services.turnstile.expected_hostname' => 'localhost',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(array_merge([
            'success' => true,
            'hostname' => 'localhost',
            'action' => 'register',
            'error-codes' => [],
        ], $responseData)),
    ]);

    $this->post('/register', registrationData())
        ->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
    expect(User::query()->where('email', 'turnstile@example.com')->exists())->toBeFalse();
})->with([
    'wrong action' => [['action' => 'login']],
    'wrong hostname' => [['hostname' => 'attacker.example.com']],
]);

test('missing Turnstile configuration blocks registration without making a request', function () {
    config([
        'services.turnstile.secret_key' => null,
        'services.turnstile.expected_hostname' => 'localhost',
    ]);
    Http::preventStrayRequests();

    $this->post('/register', registrationData())
        ->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
    expect(User::query()->where('email', 'turnstile@example.com')->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('connection failures and timeouts block registration after retries', function () {
    config([
        'services.turnstile.secret_key' => 'test-secret-key',
        'services.turnstile.expected_hostname' => 'localhost',
    ]);
    Http::fake(Http::failedConnection('Connection timed out.'));

    $this->post('/register', registrationData())
        ->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
    expect(User::query()->where('email', 'turnstile@example.com')->exists())->toBeFalse();
    Http::assertSentCount(3);
});

test('server errors block registration after retries', function () {
    config([
        'services.turnstile.secret_key' => 'test-secret-key',
        'services.turnstile.expected_hostname' => 'localhost',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::sequence()
            ->pushStatus(500)
            ->pushStatus(502)
            ->pushStatus(503),
    ]);

    $this->post('/register', registrationData())
        ->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
    expect(User::query()->where('email', 'turnstile@example.com')->exists())->toBeFalse();
    Http::assertSentCount(3);
});
