<?php

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ExpenseCreatedFromEmail;
use App\Services\ReceiptExpenseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\VAPID;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $keys = VAPID::createVapidKeys();
    config([
        'webpush.vapid.subject' => 'mailto:push@spendo.test',
        'webpush.vapid.public_key' => $keys['publicKey'],
        'webpush.vapid.private_key' => $keys['privateKey'],
    ]);
    $this->user = User::factory()->create();
    $this->user->updatePushSubscription('https://web.push.apple.com/test-device',
        $keys['publicKey'], rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='), 'aes128gcm');
    $receiptId = DB::table('inbound_receipts')->insertGetId([
        'user_id' => $this->user->id,
        'recipient' => 'importar@example.test',
        'content_sha256' => hash('sha256', 'receipt'),
        'storage_path' => 'receipt.eml',
        'status' => 'pending',
        'source_key' => hash('sha256', 'source'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->receipt = DB::table('inbound_receipts')->find($receiptId);
    $this->parsed = [
        'provider' => 'uber', 'currency' => 'ARS', 'amount' => '2348.00',
        'origin_address' => 'Ushuaia', 'destination_address' => 'Gaucho Rivero',
        'purchase_date' => '2026-10-23', 'source_key' => hash('sha256', 'source'),
    ];
});

test('a new imported expense notifies its owner only after commit and is not notified twice', function () {
    Notification::fake();

    DB::transaction(function () {
        app(ReceiptExpenseService::class)->createFor($this->receipt, $this->parsed);
        Notification::assertNothingSent();
    });

    $transaction = Transaction::query()->sole();
    Notification::assertSentTo($this->user, ExpenseCreatedFromEmail::class,
        fn ($notification) => $notification->transaction->id === $transaction->id);
    $receipt = DB::table('inbound_receipts')->find($this->receipt->id);
    DB::transaction(fn () => app(ReceiptExpenseService::class)->createFor($receipt, $this->parsed));
    Notification::assertSentTimes(ExpenseCreatedFromEmail::class, 1);
});

test('a rollback sends no push and creates no expense', function () {
    Notification::fake();

    try {
        DB::transaction(function () {
            app(ReceiptExpenseService::class)->createFor($this->receipt, $this->parsed);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Notification::assertNothingSent();
    $this->assertDatabaseCount('transactions', 0);
});

test('pending or invalid receipts do not notify', function () {
    Notification::fake();
    app(ReceiptExpenseService::class)->createFor($this->receipt, null);
    $this->parsed['source_key'] = null;
    app(ReceiptExpenseService::class)->createFor($this->receipt, $this->parsed);
    Notification::assertNothingSent();
    $this->assertDatabaseCount('transactions', 0);
});

test('users without devices or configured push still receive their imported expense', function (bool $configured) {
    Notification::fake();

    if ($configured) {
        $this->user->pushSubscriptions()->delete();
    } else {
        config(['webpush.vapid.private_key' => null]);
    }

    DB::transaction(fn () => app(ReceiptExpenseService::class)->createFor($this->receipt, $this->parsed));
    Notification::assertNothingSent();
    $this->assertDatabaseCount('transactions', 1);
})->with([true, false]);

test('a queue dispatch failure does not undo an imported expense', function () {
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue unavailable'));

    DB::transaction(fn () => app(ReceiptExpenseService::class)->createFor($this->receipt, $this->parsed));

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseHas('inbound_receipts', ['id' => $this->receipt->id, 'status' => 'imported']);
});

test('the expense notification uses the push channel, a stable tag and its edit URL', function () {
    Notification::fake();
    DB::transaction(fn () => app(ReceiptExpenseService::class)->createFor($this->receipt, $this->parsed));
    $transaction = Transaction::query()->sole();
    $notification = new ExpenseCreatedFromEmail($transaction);
    $payload = $notification->toWebPush($this->user, $notification)->toArray();

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($this->user))->toBe([WebPushChannel::class])
        ->and($payload['title'])->toBe('Gasto registrado desde un recibo')
        ->and($payload['body'])->toBe('Uber · ARS 2.348,00')
        ->and($payload['tag'])->toBe('receipt-expense-'.$transaction->id)
        ->and($payload['data']['url'])->toBe('/app/transactions/'.$transaction->id.'/edit');
});

test('the push channel sends encrypted HTTP messages and removes expired subscriptions', function () {
    Notification::fake();
    DB::transaction(fn () => app(ReceiptExpenseService::class)->createFor($this->receipt, $this->parsed));
    Http::fake(['web.push.apple.com/*' => Http::response('', 410)]);

    $notification = new ExpenseCreatedFromEmail(Transaction::query()->sole());
    app(WebPushChannel::class)->send($this->user->fresh(), $notification);

    Http::assertSent(fn ($request) => $request->url() === 'https://web.push.apple.com/test-device'
        && $request->hasHeader('Authorization')
        && ! str_contains($request->body(), 'Gasto registrado'));
    expect($this->user->pushSubscriptions()->count())->toBe(0);
});
