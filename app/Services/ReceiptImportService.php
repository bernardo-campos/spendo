<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class ReceiptImportService
{
    private const MAX_MESSAGE_BYTES = 10_000_000;

    public function __construct(
        private UberReceiptParser $uberReceiptParser,
        private DidiReceiptParser $didiReceiptParser,
        private ReceiptExpenseService $receiptExpenseService,
        private GmailForwardingVerificationParser $gmailForwardingVerificationParser,
    ) {}

    public function addressFor(User $user, bool $rotate = false): string
    {
        if ($user->receipt_import_token === null || $rotate) {
            $user->receipt_import_token = bin2hex(random_bytes(20));
            $user->save();
        }

        return $this->currentAddressFor($user);
    }

    public function currentAddressFor(User $user): ?string
    {
        if ($user->receipt_import_token === null) {
            return null;
        }

        return 'importar+'.$user->receipt_import_token.'@'.config('spendo.receipt_import_domain');
    }

    /**
     * @param  resource  $input
     */
    public function ingest(string $recipient, mixed $input): string
    {
        $recipient = strtolower(trim($recipient));
        $domain = preg_quote((string) config('spendo.receipt_import_domain'), '/');

        if (! preg_match('/^importar\+([a-f0-9]{40})@'.$domain.'$/D', $recipient, $matches)) {
            throw new InvalidArgumentException('La dirección de importación no es válida.');
        }

        $user = User::query()->where('receipt_import_token', $matches[1])->first();

        if ($user === null) {
            throw new InvalidArgumentException('La dirección de importación no existe.');
        }

        if (! is_resource($input)) {
            throw new InvalidArgumentException('La entrada del mensaje no es válida.');
        }

        $message = stream_get_contents($input, self::MAX_MESSAGE_BYTES + 1);

        if ($message === false) {
            throw new RuntimeException('No se pudo leer el mensaje entrante.');
        }

        if ($message === '') {
            throw new InvalidArgumentException('El mensaje está vacío.');
        }

        if (strlen($message) > self::MAX_MESSAGE_BYTES) {
            throw new InvalidArgumentException('El mensaje supera el tamaño permitido.');
        }

        $contentHash = hash('sha256', $message);
        $path = "inbound-receipts/{$user->id}/{$contentHash}.eml";
        $verification = $this->gmailForwardingVerificationParser->parse($message, $recipient);
        $parsed = null;

        if ($verification === null) {
            $uberReceipt = $this->uberReceiptParser->parse($message);
            $didiReceipt = $uberReceipt === null ? $this->didiReceiptParser->parse($message) : null;

            $parsed = $uberReceipt !== null
                ? ['provider' => 'uber', ...$uberReceipt]
                : ($didiReceipt === null ? null : ['provider' => 'didi', ...$didiReceipt]);
        }

        if (! Storage::disk('local')->put($path, $message)) {
            throw new RuntimeException('No se pudo guardar el mensaje entrante.');
        }

        $storedPath = DB::transaction(function () use ($user, $recipient, $contentHash, $path, $parsed, $verification): string {
            DB::table('inbound_receipts')->insertOrIgnore([
                'user_id' => $user->id,
                'recipient' => $recipient,
                'content_sha256' => $contentHash,
                'source_key' => $parsed['source_key'] ?? null,
                'storage_path' => $path,
                'status' => 'pending',
                'currency' => $parsed['currency'] ?? null,
                'amount' => $parsed['amount'] ?? null,
                'origin_address' => $parsed['origin_address'] ?? null,
                'destination_address' => $parsed['destination_address'] ?? null,
                'gmail_forwarding_source' => $verification['source'] ?? null,
                'gmail_verification_url' => $verification['url'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $receipt = ($parsed['source_key'] ?? null) !== null
                ? DB::table('inbound_receipts')
                    ->where('user_id', $user->id)
                    ->where('source_key', $parsed['source_key'])
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($receipt === null) {
                $receipt = DB::table('inbound_receipts')
                    ->where('user_id', $user->id)
                    ->where('content_sha256', $contentHash)
                    ->lockForUpdate()
                    ->first();
            }

            if ($receipt === null) {
                throw new RuntimeException('No se pudo recuperar el recibo entrante.');
            }

            if ($receipt->status === 'pending' && $parsed !== null) {
                DB::table('inbound_receipts')->where('id', $receipt->id)->update([
                    'source_key' => $parsed['source_key'] ?? $receipt->source_key,
                    'currency' => $parsed['currency'],
                    'amount' => $parsed['amount'],
                    'origin_address' => $parsed['origin_address'],
                    'destination_address' => $parsed['destination_address'],
                    'updated_at' => now(),
                ]);
            }

            if ($verification !== null && $receipt->status === 'pending') {
                DB::table('inbound_receipts')->where('id', $receipt->id)->update([
                    'gmail_forwarding_source' => $verification['source'],
                    'gmail_verification_url' => $verification['url'],
                    'updated_at' => now(),
                ]);
            }

            $this->receiptExpenseService->createFor($receipt, $parsed);

            return $receipt->storage_path;
        }, attempts: 3);

        if ($storedPath !== $path && ! DB::table('inbound_receipts')->where('storage_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }

        return $storedPath;
    }
}
