<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReceiptImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('a user receives a stable private import address that can be rotated', function () {
    $user = User::factory()->create();
    $service = app(ReceiptImportService::class);

    $address = $service->addressFor($user);

    expect($address)
        ->toMatch('/^importar\+[a-f0-9]{40}@recibos\.spendo\.bernu\.dev$/')
        ->toBe($service->addressFor($user));

    expect($service->addressFor($user, rotate: true))->not->toBe($address);
});

test('a valid incoming message is saved privately and recorded once', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $service = app(ReceiptImportService::class);
    $address = $service->addressFor($user);
    $message = "From: user@example.com\r\nSubject: Viaje Uber\r\n\r\nTotal: 100";
    $input = fopen('php://temp', 'w+');
    fwrite($input, $message);
    rewind($input);

    $path = $service->ingest($address, $input);

    expect(Storage::disk('local')->get($path))->toBe($message);

    $this->assertDatabaseHas('inbound_receipts', [
        'user_id' => $user->id,
        'recipient' => $address,
        'storage_path' => $path,
        'status' => 'pending',
    ]);

    rewind($input);
    expect($service->ingest($address, $input))->toBe($path);
    expect(DB::table('inbound_receipts')->count())->toBe(1);
    expect(Transaction::query()->count())->toBe(0);

    fclose($input);
});

test('a manually forwarded Uber receipt creates one cash expense', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Transporte',
        'slug' => 'transporte',
        'scope' => 'expense',
        'is_active' => true,
    ]);
    $this->actingAs($user)->putJson('/receipt-import-settings/uber', [
        'category_id' => $category->id,
        'place' => 'Viajes por aplicación',
    ])->assertSuccessful();
    $address = app(ReceiptImportService::class)->addressFor($user);
    $html = '<html><body>'
        .'<td class="m_-7930254747577144633total-fare-amount">ARS&nbsp;2,348.00</td>'
        .'<td class="m_-7930254747577144633address-point-desc">Ushuaia</td>'
        .'<td class="m_-7930254747577144633address-point-desc"><a href="https://example.test/map">Gaucho Rivero</a></td>'
        .'<td><a href="https://click.uber.com/f/a/receipt-one"></a>Descargar&nbsp;PDF</td>'
        .'</body></html>';
    $message = "Date: Thu, 15 Oct 2026 02:29:14 -0300\r\nContent-Type: multipart/alternative; boundary=\"receipt-boundary\"\r\n\r\n"
        ."--receipt-boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nTotal ARS 2,590.00\r\n"
        ."--receipt-boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        .quoted_printable_encode($html)."\r\n--receipt-boundary--\r\n";
    $input = fopen('php://temp', 'w+');
    fwrite($input, $message);
    rewind($input);

    app(ReceiptImportService::class)->ingest($address, $input);

    $this->assertDatabaseHas('inbound_receipts', [
        'user_id' => $user->id,
        'status' => 'imported',
        'currency' => 'ARS',
        'amount' => '2348.00',
        'origin_address' => 'Ushuaia',
        'destination_address' => 'Gaucho Rivero',
        'source_key' => hash('sha256', 'https://click.uber.com/f/a/receipt-one'),
    ]);

    $transaction = Transaction::query()->sole();

    expect($transaction->type)->toBe('expense')
        ->and($transaction->category_id)->toBe($category->id)
        ->and($transaction->description)->toBe('Viaje Uber: Ushuaia → Gaucho Rivero')
        ->and($transaction->place)->toBe('Viajes por aplicación')
        ->and($transaction->payment_method)->toBe('cash')
        ->and($transaction->amount)->toBe('2348.00')
        ->and($transaction->currency->value)->toBe('ARS')
        ->and($transaction->purchase_date->toDateString())->toBe('2026-10-15')
        ->and($transaction->payment_date->toDateString())->toBe('2026-10-15');
    expect(DB::table('inbound_receipts')->value('transaction_id'))->toBe($transaction->id);

    $original = '<html><head><meta charset="utf-8"></head><body>'
        .'<td data-testid="total_fare_amount">ARS&nbsp;2,348.00</td>'
        .'<td data-testid="address_point_0_address">Ushuaia</td>'
        .'<td data-testid="address_point_1_address">Gaucho Rivero</td>'
        .'<a href="https://click.uber.com/f/a/receipt-one">Descargar&nbsp;PDF</a>'
        .'</body></html>';
    $secondMessage = "Date: Sun, 11 Oct 2026 23:12:06 +0000\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($original);
    $secondInput = fopen('php://temp', 'w+');
    fwrite($secondInput, $secondMessage);
    rewind($secondInput);
    app(ReceiptImportService::class)->ingest($address, $secondInput);

    expect(Transaction::query()->count())->toBe(1)
        ->and(DB::table('inbound_receipts')->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);

    $transaction->delete();
    rewind($secondInput);
    app(ReceiptImportService::class)->ingest($address, $secondInput);

    expect(Transaction::query()->count())->toBe(0);

    fclose($input);
    fclose($secondInput);
});

test('a forwarded DiDi receipt creates one configured expense and the original is deduplicated', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Transporte',
        'slug' => 'transporte',
        'scope' => 'expense',
        'is_active' => true,
    ]);
    $this->actingAs($user)->putJson('/receipt-import-settings/didi', [
        'category_id' => $category->id,
        'place' => 'Viajes por aplicación',
    ])->assertSuccessful();
    $this->getJson('/receipt-import-settings')->assertJsonPath('providers.1.key', 'didi');

    $address = app(ReceiptImportService::class)->addressFor($user);
    $html = '<html><body><p>dom, 10 may, 2026</p><table><tr><td>Total</td><td>$3,320.00</td></tr></table>'
        .'<div><strong><span>07:20 pm</span></strong></div><div>La Campiña Fiambres Y Lacteos</div>'
        .'<div><strong><span>07:38 pm</span></strong></div><div>Gaucho Rivero 352</div>'
        .'<td><a href="https://mpub-proxy.didiglobal.com/hera/click?d=receipt-one"></a>Duda sobre la tarifa</td>'
        .'</body></html>';
    $forwarded = "From: Usuario <user@example.com>\r\nDate: Thu, 15 Oct 2026 14:28:18 -0300\r\n"
        ."Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        .quoted_printable_encode($html);
    $forwardedInput = fopen('php://temp', 'w+');
    fwrite($forwardedInput, $forwarded);
    rewind($forwardedInput);
    app(ReceiptImportService::class)->ingest($address, $forwardedInput);

    $this->assertDatabaseHas('inbound_receipts', [
        'user_id' => $user->id,
        'status' => 'imported',
        'currency' => 'ARS',
        'amount' => '3320.00',
        'origin_address' => 'La Campiña Fiambres Y Lacteos',
        'destination_address' => 'Gaucho Rivero 352',
    ]);
    $transaction = Transaction::query()->sole();
    expect($transaction->description)->toBe('Viaje DiDi: La Campiña Fiambres Y Lacteos → Gaucho Rivero')
        ->and($transaction->place)->toBe('Viajes por aplicación')
        ->and($transaction->category_id)->toBe($category->id)
        ->and($transaction->amount)->toBe('3320.00')
        ->and($transaction->currency->value)->toBe('ARS')
        ->and($transaction->payment_method)->toBe('cash')
        ->and($transaction->purchase_date->toDateString())->toBe('2026-10-15')
        ->and($transaction->notes)->toContain('La Campiña Fiambres Y Lacteos', 'Gaucho Rivero 352');

    $original = "From: DiDi <didi@ar.didiglobal.com>\r\nDate: Sun, 10 May 2026 22:39:09 +0000\r\n"
        ."Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        .base64_encode($html);
    $originalInput = fopen('php://temp', 'w+');
    fwrite($originalInput, $original);
    rewind($originalInput);
    app(ReceiptImportService::class)->ingest($address, $originalInput);

    expect(Transaction::query()->count())->toBe(1)
        ->and(DB::table('inbound_receipts')->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);

    fclose($forwardedInput);
    fclose($originalInput);
});

test('a previously pending receipt is imported when delivered again after parser support is added', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $service = app(ReceiptImportService::class);
    $address = $service->addressFor($user);
    $html = '<td class="m_-7930254747577144633total-fare-amount">ARS&nbsp;2,348.00</td>'
        .'<a href="https://tracking.ibt.uber.com/tracking/1/click/pending-receipt">Descargar PDF</a>';
    $message = "Date: Thu, 15 Oct 2026 03:42:37 -0300 (ART)\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}";
    $hash = hash('sha256', $message);
    $path = "inbound-receipts/{$user->id}/{$hash}.eml";
    Storage::disk('local')->put($path, $message);
    DB::table('inbound_receipts')->insert([
        'user_id' => $user->id,
        'recipient' => $address,
        'content_sha256' => $hash,
        'storage_path' => $path,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $input = fopen('php://temp', 'w+');
    fwrite($input, $message);
    rewind($input);
    $service->ingest($address, $input);

    $this->assertDatabaseHas('inbound_receipts', [
        'user_id' => $user->id,
        'status' => 'imported',
        'amount' => '2348.00',
        'source_key' => hash('sha256', 'https://tracking.ibt.uber.com/tracking/1/click/pending-receipt'),
    ]);
    expect(Transaction::query()->count())->toBe(1);

    fclose($input);
});

test('a receipt without a stable Uber identifier stays pending', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $address = app(ReceiptImportService::class)->addressFor($user);
    $message = "Date: Thu, 15 Oct 2026 02:29:14 -0300\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
        .'<html><body><td data-testid="total_fare_amount">ARS&nbsp;2,348.00</td></body></html>';
    $input = fopen('php://temp', 'w+');
    fwrite($input, $message);
    rewind($input);

    app(ReceiptImportService::class)->ingest($address, $input);

    $this->assertDatabaseHas('inbound_receipts', [
        'user_id' => $user->id,
        'status' => 'pending',
        'amount' => '2348.00',
        'source_key' => null,
        'transaction_id' => null,
    ]);
    expect(Transaction::query()->count())->toBe(0);

    fclose($input);
});

test('a zero-amount receipt does not create an expense', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $address = app(ReceiptImportService::class)->addressFor($user);
    $message = "Date: Thu, 15 Oct 2026 02:29:14 -0300\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
        .'<html><body><td data-testid="total_fare_amount">ARS&nbsp;0.00</td>'
        .'<a href="https://click.uber.com/f/a/zero-receipt">Descargar PDF</a></body></html>';
    $input = fopen('php://temp', 'w+');
    fwrite($input, $message);
    rewind($input);

    app(ReceiptImportService::class)->ingest($address, $input);

    expect(Transaction::query()->count())->toBe(0);
    $this->assertDatabaseHas('inbound_receipts', ['user_id' => $user->id, 'status' => 'pending']);

    fclose($input);
});

test('unknown or revoked import addresses are rejected', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $service = app(ReceiptImportService::class);
    $oldAddress = $service->addressFor($user);
    $service->addressFor($user, rotate: true);

    $input = fopen('php://temp', 'w+');
    fwrite($input, "Subject: Test\r\n\r\nBody");
    rewind($input);

    expect(fn () => $service->ingest($oldAddress, $input))
        ->toThrow(InvalidArgumentException::class);
    expect(DB::table('inbound_receipts')->count())->toBe(0);

    fclose($input);
});

test('the ingest command rejects an invalid recipient before reading stdin', function () {
    expect(Artisan::call('receipts:ingest', ['recipient' => 'otro@example.com']))->toBe(67);
});

test('oversized incoming messages are rejected before storage', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $service = app(ReceiptImportService::class);
    $input = fopen('php://temp', 'w+');
    fwrite($input, str_repeat('x', 10_000_001));
    rewind($input);

    expect(fn () => $service->ingest($service->addressFor($user), $input))
        ->toThrow(InvalidArgumentException::class);
    expect(DB::table('inbound_receipts')->count())->toBe(0);

    fclose($input);
});
