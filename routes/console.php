<?php

use App\Models\User;
use App\Services\ReceiptImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('receipts:address {user} {--rotate}', function (string $user) {
    $account = User::query()->findOrFail($user);

    $this->line(app(ReceiptImportService::class)->addressFor($account, (bool) $this->option('rotate')));
})->purpose('Mostrar o regenerar la dirección de importación de un usuario');

Artisan::command('receipts:ingest {recipient}', function (string $recipient) {
    try {
        app(ReceiptImportService::class)->ingest($recipient, STDIN);

        return 0;
    } catch (InvalidArgumentException $exception) {
        $this->error($exception->getMessage());

        return 67;
    } catch (Throwable $exception) {
        report($exception);
        $this->error('No se pudo guardar el recibo.');

        return 75;
    }
})->purpose('Guardar un correo entrante entregado por Postfix');
