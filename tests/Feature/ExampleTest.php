<?php

use App\Models\User;

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

test('the welcome view links authenticated users to the application', function () {
    $this->actingAs(User::factory()->make());

    $this->view('welcome')
        ->assertSee('href="'.route('app').'"', false)
        ->assertDontSee('href="'.url('/dashboard').'"', false);
});
