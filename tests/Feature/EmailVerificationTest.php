<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Fortify;

uses(LazilyRefreshDatabase::class);

test('registration sends an email verification notification', function () {
    Notification::fake();
    fakeSuccessfulTurnstile();

    $this->post('/register', [
        'name' => 'Unverified User',
        'email' => 'unverified@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'cf-turnstile-response' => 'valid-turnstile-token',
    ])->assertRedirect('/app');

    $user = User::query()->where('email', 'unverified@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('unverified users are redirected to the verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/app')
        ->assertRedirect('/email/verify');

    $this->get('/email/verify')
        ->assertSuccessful()
        ->assertSee('Verificá tu email')
        ->assertSee($user->email);
});

test('unverified users can request another verification email', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from('/email/verify')
        ->post('/email/verification-notification')
        ->assertRedirect('/email/verify')
        ->assertSessionHas('status', Fortify::VERIFICATION_LINK_SENT);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('user can verify the email with a valid signed link', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect('/app?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

test('email cannot be verified with an invalid signature', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = route('verification.verify', [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified users can access the application', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/app')
        ->assertSuccessful();
});
