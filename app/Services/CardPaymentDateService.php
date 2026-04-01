<?php

namespace App\Services;

use App\Enums\PaymentMethodType;
use App\Models\Card;
use Carbon\CarbonImmutable;

class CardPaymentDateService
{
    /**
     * @return array{date:string,is_estimated:bool}
     */
    public function resolve(string $purchaseDate, string $paymentMethod, ?Card $card): array
    {
        $purchase = CarbonImmutable::parse($purchaseDate);

        if ($paymentMethod !== PaymentMethodType::Credit->value || $card === null) {
            return [
                'date' => $purchase->toDateString(),
                'is_estimated' => false,
            ];
        }

        $cycle = $card->billingCycles()
            ->whereDate('closing_date', '>=', $purchase->toDateString())
            ->orderBy('closing_date')
            ->first();

        if ($cycle !== null) {
            return [
                'date' => $cycle->due_date->toDateString(),
                'is_estimated' => false,
            ];
        }

        $closingDay = $card->closing_day ?? 1;
        $dueDay = $card->due_day ?? $closingDay;

        $statementMonth = $purchase->day <= $closingDay
            ? $purchase->startOfMonth()
            : $purchase->addMonthNoOverflow()->startOfMonth();

        $dueMonth = $statementMonth->addMonthNoOverflow();
        $lastDayOfDueMonth = $dueMonth->endOfMonth()->day;
        $safeDueDay = min($dueDay, $lastDayOfDueMonth);

        return [
            'date' => $dueMonth->setDay($safeDueDay)->toDateString(),
            'is_estimated' => true,
        ];
    }
}
