<?php

namespace App\Services;

use App\Enums\PaymentMethodType;
use App\Enums\TransactionCurrency;
use App\Models\Transaction;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class ReceiptExpenseService
{
    /**
     * @param  array{provider: 'uber'|'didi', currency: string, amount: string, origin_address: ?string, destination_address: ?string, purchase_date: ?string, source_key: ?string}|null  $parsed
     */
    public function createFor(stdClass $receipt, ?array $parsed): void
    {
        if (
            $receipt->transaction_id !== null
            || $receipt->status === 'imported'
            || $parsed === null
            || $parsed['source_key'] === null
            || $parsed['purchase_date'] === null
        ) {
            return;
        }

        $currency = TransactionCurrency::tryFrom($parsed['currency']);

        if ($currency === null) {
            return;
        }

        if ((float) $parsed['amount'] <= 0 || strlen(Str::before($parsed['amount'], '.')) > 10) {
            return;
        }

        $origin = $this->shortAddress($parsed['origin_address']);
        $destination = $this->shortAddress($parsed['destination_address']);
        $providerName = $parsed['provider'] === 'didi' ? 'DiDi' : 'Uber';
        $description = $origin !== null && $destination !== null
            ? Str::limit("Viaje {$providerName}: {$origin} → {$destination}", 255, '')
            : "Viaje {$providerName}";
        $notes = $parsed['origin_address'] !== null && $parsed['destination_address'] !== null
            ? Str::limit("Desde: {$parsed['origin_address']}\nHasta: {$parsed['destination_address']}", 5000, '')
            : null;
        $settings = DB::table('receipt_import_settings')
            ->leftJoin('categories', function (JoinClause $join) use ($receipt): void {
                $join->on('categories.id', '=', 'receipt_import_settings.category_id')
                    ->where('categories.user_id', $receipt->user_id)
                    ->where('categories.is_active', true)
                    ->whereIn('categories.scope', ['expense', 'both']);
            })
            ->where('receipt_import_settings.user_id', $receipt->user_id)
            ->where('receipt_import_settings.provider', $parsed['provider'])
            ->first(['receipt_import_settings.place', 'categories.id as category_id']);

        $transaction = Transaction::query()->create([
            'user_id' => $receipt->user_id,
            'category_id' => $settings?->category_id,
            'type' => 'expense',
            'description' => $description,
            'place' => $settings?->place ?? $providerName,
            'amount' => $parsed['amount'],
            'currency' => $currency,
            'purchase_date' => $parsed['purchase_date'],
            'payment_date' => $parsed['purchase_date'],
            'payment_method' => PaymentMethodType::Cash->value,
            'notes' => $notes,
        ]);

        DB::table('inbound_receipts')->where('id', $receipt->id)->update([
            'transaction_id' => $transaction->id,
            'status' => 'imported',
            'updated_at' => now(),
        ]);
    }

    private function shortAddress(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $firstPart = trim(Str::before($address, ','));
        $street = trim(preg_replace('/\s+\d+$/u', '', $firstPart) ?? $firstPart);

        return $street === '' ? null : $street;
    }
}
