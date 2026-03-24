<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest can register and gets redirected to app', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/app');

    $this->assertAuthenticated();
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
