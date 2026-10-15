<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\GmailForwardingVerificationParser;
use App\Services\ReceiptImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function gmailVerificationMessage(string $recipient, string $approvalUrl): string
{
    return "From: Gmail Team <forwarding-noreply@google.com>\r\n"
        ."To: {$recipient}\r\n"
        ."Subject: (Gmail Forwarding Confirmation - Receive Mail from sender@example.com\r\n"
        ."Content-Type: text/plain; charset=\"UTF-8\"\r\n\r\n"
        ."sender@example.com has requested to automatically forward mail to your email\r\n"
        ."address {$recipient}.\r\n\r\n"
        ."To allow this, click the link below:\r\n{$approvalUrl}\r\n\r\n"
        .'If you do not approve, use https://mail-settings.google.com/mail/uf-cancel-token.';
}

test('the parser accepts only the Gmail approval link for the intended recipient', function () {
    $recipient = 'importar+abcdef@recibos.spendo.bernu.dev';
    $url = 'https://mail-settings.google.com/mail/vf-approval-token';
    $message = gmailVerificationMessage($recipient, $url);
    $parser = app(GmailForwardingVerificationParser::class);

    expect($parser->parse($message, $recipient))->toBe([
        'source' => 'sender@example.com',
        'url' => $url,
    ]);
    expect($parser->parse($message, 'importar+other@recibos.spendo.bernu.dev'))->toBeNull();
    expect($parser->parse(str_replace('forwarding-noreply@google.com', 'attacker@example.com', $message), $recipient))->toBeNull();
    expect($parser->parse(str_replace('/mail/vf-', '/mail/uf-', $message), $recipient))->toBeNull();
    expect($parser->parse(str_replace('mail-settings.google.com', 'mail-settings.google.com.evil.test', $message), $recipient))->toBeNull();
});

test('the recipient can see the Gmail approval link without creating an expense', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $address = app(ReceiptImportService::class)->addressFor($user);
    $url = 'https://mail-settings.google.com/mail/vf-approval-token';
    $message = gmailVerificationMessage($address, $url);
    $input = fopen('php://temp', 'w+');
    fwrite($input, $message);
    rewind($input);

    app(ReceiptImportService::class)->ingest($address, $input);

    $this->assertDatabaseHas('inbound_receipts', [
        'user_id' => $user->id,
        'recipient' => $address,
        'status' => 'pending',
        'gmail_forwarding_source' => 'sender@example.com',
        'gmail_verification_url' => $url,
    ]);
    expect(Transaction::query()->count())->toBe(0);

    $this->actingAs($user)->getJson('/receipt-import-verifications')
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('verifications.0.source', 'sender@example.com')
        ->assertJsonPath('verifications.0.url', $url);
    $this->actingAs($otherUser)->getJson('/receipt-import-verifications')
        ->assertJsonCount(0, 'verifications');

    app(ReceiptImportService::class)->addressFor($user, rotate: true);
    $this->actingAs($user)->getJson('/receipt-import-verifications')
        ->assertJsonCount(0, 'verifications');

    fclose($input);
});

test('a previously stored verification message is recognized when delivered again', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $address = app(ReceiptImportService::class)->addressFor($user);
    $message = gmailVerificationMessage($address, 'https://mail-settings.google.com/mail/vf-existing-token');
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
    app(ReceiptImportService::class)->ingest($address, $input);

    $this->assertDatabaseHas('inbound_receipts', [
        'user_id' => $user->id,
        'gmail_verification_url' => 'https://mail-settings.google.com/mail/vf-existing-token',
    ]);
    expect(DB::table('inbound_receipts')->count())->toBe(1);

    fclose($input);
});

test('dismissing a verification hides only that notice without changing its receipt status', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $address = app(ReceiptImportService::class)->addressFor($user);
    $firstUrl = 'https://mail-settings.google.com/mail/vf-first-token';
    $firstInput = fopen('php://temp', 'w+');
    fwrite($firstInput, gmailVerificationMessage($address, $firstUrl));
    rewind($firstInput);
    app(ReceiptImportService::class)->ingest($address, $firstInput);
    fclose($firstInput);

    $verification = DB::table('inbound_receipts')->where('gmail_verification_url', $firstUrl)->first();

    $this->actingAs($otherUser)
        ->patchJson("/receipt-import-verifications/{$verification->id}/dismiss")
        ->assertNotFound();
    $this->actingAs($user)
        ->patchJson("/receipt-import-verifications/{$verification->id}/dismiss")
        ->assertNoContent();

    $this->assertDatabaseHas('inbound_receipts', [
        'id' => $verification->id,
        'status' => 'pending',
        'gmail_verification_url' => $firstUrl,
    ]);
    expect(DB::table('inbound_receipts')->where('id', $verification->id)->value('gmail_verification_dismissed_at'))->not->toBeNull();
    $this->getJson('/receipt-import-verifications')->assertJsonCount(0, 'verifications');

    $secondUrl = 'https://mail-settings.google.com/mail/vf-second-token';
    $secondInput = fopen('php://temp', 'w+');
    fwrite($secondInput, gmailVerificationMessage($address, $secondUrl));
    rewind($secondInput);
    app(ReceiptImportService::class)->ingest($address, $secondInput);
    fclose($secondInput);

    $this->getJson('/receipt-import-verifications')
        ->assertJsonCount(1, 'verifications')
        ->assertJsonPath('verifications.0.url', $secondUrl);
});

test('verification links require an authenticated verified session', function () {
    $this->getJson('/receipt-import-verifications')->assertUnauthorized();
    $this->patchJson('/receipt-import-verifications/1/dismiss')->assertUnauthorized();

    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get('/receipt-import-verifications')->assertRedirect('/email/verify');
    $this->patch('/receipt-import-verifications/1/dismiss')->assertRedirect('/email/verify');
});
