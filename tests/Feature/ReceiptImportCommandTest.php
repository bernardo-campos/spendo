<?php

use Symfony\Component\Process\Process;

test('the artisan command creates one expense from stdin using an isolated local database', function () {
    $databasePath = tempnam(sys_get_temp_dir(), 'spendo-receipt-');
    $storedMessagePath = null;
    $connection = null;
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $databasePath,
        'DB_URL' => '',
    ];

    try {
        $migration = new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--force', '--no-interaction'], base_path(), $environment);
        $migration->run();

        expect($migration->isSuccessful())->toBeTrue($migration->getErrorOutput().$migration->getOutput());

        $connection = new PDO('sqlite:'.$databasePath);
        $token = bin2hex(random_bytes(20));
        $insertUser = $connection->prepare('INSERT INTO users (name, email, password, receipt_import_token) VALUES (?, ?, ?, ?)');
        $insertUser->execute(['Prueba', 'receipt-test@example.com', 'unused', $token]);
        $address = 'importar+'.$token.'@recibos.spendo.bernu.dev';
        $nonce = bin2hex(random_bytes(8));
        $html = '<html><body>'
            .'<td data-testid="total_fare_amount">ARS&nbsp;2,348.00</td>'
            .'<td data-testid="address_point_0_address">Ushuaia</td>'
            .'<td data-testid="address_point_1_address">Gaucho Rivero</td>'
            .'<a href="https://click.uber.com/f/a/'.$nonce.'">Descargar&nbsp;PDF</a>'
            .'</body></html>';
        $message = "From: sender@example.com\r\nDate: Thu, 15 Oct 2026 02:29:14 -0300\r\n"
            ."Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            .quoted_printable_encode($html);

        $ingestion = new Process([PHP_BINARY, base_path('artisan'), 'receipts:ingest', $address], base_path(), $environment);
        $ingestion->setInput($message);
        $ingestion->run();

        expect($ingestion->isSuccessful())->toBeTrue($ingestion->getErrorOutput().$ingestion->getOutput());

        $storedPath = $connection->query('SELECT storage_path FROM inbound_receipts')->fetchColumn();
        expect($storedPath)->toBeString();

        $storedMessagePath = storage_path('app/private/'.$storedPath);
        expect(file_get_contents($storedMessagePath))->toBe($message);
        $expense = $connection->query('SELECT amount, currency, purchase_date, payment_method FROM transactions')->fetch(PDO::FETCH_ASSOC);
        expect($expense['amount'])->toBe(2348)
            ->and($expense['currency'])->toBe('ARS')
            ->and(substr($expense['purchase_date'], 0, 10))->toBe('2026-10-15')
            ->and($expense['payment_method'])->toBe('cash');
        expect($connection->query('SELECT status FROM inbound_receipts')->fetchColumn())->toBe('imported');

        $duplicate = new Process([PHP_BINARY, base_path('artisan'), 'receipts:ingest', $address], base_path(), $environment);
        $duplicate->setInput($message);
        $duplicate->run();

        expect($duplicate->isSuccessful())->toBeTrue($duplicate->getErrorOutput().$duplicate->getOutput());
        expect((int) $connection->query('SELECT COUNT(*) FROM inbound_receipts')->fetchColumn())->toBe(1);
        expect((int) $connection->query('SELECT COUNT(*) FROM transactions')->fetchColumn())->toBe(1);
    } finally {
        $insertUser = null;
        $connection = null;

        if ($storedMessagePath !== null && file_exists($storedMessagePath)) {
            unlink($storedMessagePath);
        }

        if (file_exists($databasePath)) {
            unlink($databasePath);
        }
    }
});
