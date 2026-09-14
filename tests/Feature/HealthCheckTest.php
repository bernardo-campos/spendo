<?php

test('health check is publicly available', function () {
    $response = $this->getJson('/health');

    $response
        ->assertSuccessful()
        ->assertExactJson(['status' => 'up']);
});

test('health check allows cross-origin requests', function () {
    $origin = 'https://status.example.com';

    $this->getJson('/health', ['Origin' => $origin])
        ->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', '*');

    $this->options('/health', [], [
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])
        ->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'GET');
});
