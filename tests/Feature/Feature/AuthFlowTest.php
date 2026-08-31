<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(LazilyRefreshDatabase::class);

test('guests can render the authentication views', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee('Iniciar sesión')
        ->assertSee('href="'.asset('favicon.ico').'"', false)
        ->assertSee('href="'.asset('site.webmanifest').'"', false)
        ->assertSee('Recordar mi cuenta')
        ->assertSee('<hr', false)
        ->assertSeeInOrder(['¿Olvidaste tu contraseña?', '¿No tienes cuenta?']);

    $this->get('/register')
        ->assertSuccessful()
        ->assertSee('Crear cuenta');

    $this->get('/forgot-password')
        ->assertSuccessful()
        ->assertSee('Recuperar contraseña')
        ->assertSee('data-password-reset-form', false)
        ->assertSee('data-password-reset-spinner', false)
        ->assertSee('Enviando...');

    $this->get('/reset-password/example-token?email=reset%40example.com')
        ->assertSuccessful()
        ->assertSee('Restablecer contraseña')
        ->assertSee('reset@example.com');
});

test('authentication views enforce guest and auth middleware', function () {
    $this->get('/user/confirm-password')->assertRedirect('/login');

    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/login')->assertRedirect();
    $this->get('/register')->assertRedirect();
    $this->get('/user/confirm-password')
        ->assertSuccessful()
        ->assertSee('Confirmar contraseña');
});

test('guest can register and gets redirected to app', function () {
    fakeSuccessfulTurnstile();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'cf-turnstile-response' => 'valid-turnstile-token',
    ])->assertRedirect('/app');

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->assertModelExists($user);
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('registration validates duplicate email and password confirmation', function () {
    fakeSuccessfulTurnstile();

    User::factory()->create(['email' => 'existing@example.com']);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different-password',
        'cf-turnstile-response' => 'valid-turnstile-token',
    ])->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

test('existing user can login', function () {
    User::factory()->create([
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $this->post('/login', [
        'email' => 'login@example.com',
        'password' => 'password123',
    ])->assertRedirect('/app');

    $this->assertAuthenticated();
});

test('login regenerates the session', function () {
    User::factory()->create([
        'email' => 'session@example.com',
        'password' => 'password123',
    ]);

    $this->get('/login');
    $previousSessionId = session()->getId();

    $this->post('/login', [
        'email' => 'session@example.com',
        'password' => 'password123',
    ])->assertRedirect('/app');

    expect(session()->getId())->not->toBe($previousSessionId);
});

test('invalid credentials keep the email and do not authenticate the user', function () {
    User::factory()->create([
        'email' => 'invalid@example.com',
        'password' => 'password123',
    ]);

    $this->from('/login')->post('/login', [
        'email' => 'invalid@example.com',
        'password' => 'incorrect-password',
    ])->assertRedirect('/login')
        ->assertSessionHasErrors([
            'email' => 'Las credenciales no son válidas.',
        ])
        ->assertSessionHasInput('email', 'invalid@example.com');

    $this->assertGuest();
});

test('existing user can login and be remembered', function () {
    $user = User::factory()->create([
        'email' => 'remember@example.com',
        'password' => 'password123',
    ]);

    $response = $this->post('/login', [
        'email' => 'remember@example.com',
        'password' => 'password123',
        'remember' => '1',
    ]);

    $response
        ->assertRedirect('/app')
        ->assertCookie(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->getRememberToken())->not->toBeNull()->not->toBe('');
});

test('authenticated user can logout and invalidate the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth-marker' => 'active'])
        ->post('/logout')
        ->assertRedirect('/')
        ->assertSessionMissing('auth-marker');

    $this->assertGuest();
});

test('user can request a password reset link', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->post('/forgot-password', [
        'email' => 'reset@example.com',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Te enviamos el enlace para restablecer tu contraseña.');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('user can reset the password with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'valid-reset@example.com',
        'password' => 'password123',
    ]);

    $this->post('/forgot-password', ['email' => $user->email]);

    $token = null;

    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertRedirect('/login')
        ->assertSessionHas('status', 'Tu contraseña fue restablecida.');

    expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue();
});

test('password cannot be reset with an invalid token', function () {
    $user = User::factory()->create([
        'email' => 'invalid-reset@example.com',
        'password' => 'password123',
    ]);

    $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('authenticated user can confirm the password', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->actingAs($user)
        ->post('/user/confirm-password', ['password' => 'password123'])
        ->assertRedirect();

    expect(session('auth.password_confirmed_at'))->toBeInt();
});

test('password confirmation rejects an invalid password', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->actingAs($user)
        ->from('/user/confirm-password')
        ->post('/user/confirm-password', ['password' => 'incorrect-password'])
        ->assertRedirect('/user/confirm-password')
        ->assertSessionHasErrors('password');

    expect(session('auth.password_confirmed_at'))->toBeNull();
});
