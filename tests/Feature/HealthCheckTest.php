<?php

test('health check is publicly available', function () {
    $response = $this->getJson('/health');

    $response
        ->assertSuccessful()
        ->assertExactJson(['status' => 'up']);
});
