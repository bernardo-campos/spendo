<?php

test('the application uses Buenos Aires timezone', function () {
    expect(config('app.timezone'))->toBe('America/Argentina/Buenos_Aires');
});
