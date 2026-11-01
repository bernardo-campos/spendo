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

        $cycle = $this->schedule($purchase->toDateString(), $card, 1)[0];

        return ['date' => $cycle['due_date'], 'is_estimated' => $cycle['is_estimated']];
    }

    /**
     * @return list<array{closing_date:string,due_date:string,is_estimated:bool}>
     */
    public function schedule(string $purchaseDate, Card $card, int $count): array
    {
        $card->loadMissing('billingCycles');
        $realCycles = $card->billingCycles->sortBy('closing_date')->values();
        $cycles = $realCycles->map(fn ($cycle): array => [
            'closing_date' => $cycle->closing_date->toDateString(),
            'due_date' => $cycle->due_date->toDateString(),
            'is_estimated' => false,
        ]);
        $month = CarbonImmutable::parse($purchaseDate)->startOfMonth();
        $closingDay = $card->closing_day ?? 1;
        $dueDay = $card->due_day ?? $closingDay;
        $coveredIntervals = $realCycles->sliding(2)->filter(function ($pair): bool {
            $previous = $pair->first()->due_date->toImmutable()->startOfMonth();
            $next = $pair->last()->due_date->toImmutable()->startOfMonth();

            return $previous->diffInMonths($next) >= 0 && $previous->diffInMonths($next) <= 1;
        });
        $replacedMonths = $card->billingCycles->map(function ($cycle) use ($closingDay): string {
            $closing = $cycle->closing_date->toImmutable();

            return collect([-1, 0, 1])->map(function (int $offset) use ($closing, $closingDay): CarbonImmutable {
                $candidate = $closing->startOfMonth()->addMonthsNoOverflow($offset);

                return $candidate->setDay(min($closingDay, $candidate->daysInMonth));
            })->sortBy(fn (CarbonImmutable $candidate): float => abs($candidate->diffInDays($closing)))
                ->first()->format('Y-m');
        })->all();

        for ($offset = 0; $cycles->where('closing_date', '>=', $purchaseDate)->count() < $count || $offset <= $count + 1; $offset++) {
            $closingMonth = $month->addMonthsNoOverflow($offset);
            $dueMonth = $closingDay === $dueDay ? $closingMonth : $closingMonth->addMonthNoOverflow();
            $closingDate = $closingMonth->setDay(min($closingDay, $closingMonth->daysInMonth))->toDateString();

            if (in_array($closingMonth->format('Y-m'), $replacedMonths, true)
                || $coveredIntervals->contains(fn ($pair): bool => $closingDate > $pair->first()->closing_date->toDateString()
                    && $closingDate < $pair->last()->closing_date->toDateString())) {
                continue;
            }

            $cycles->push([
                'closing_date' => $closingDate,
                'due_date' => $dueMonth->setDay(min($dueDay, $dueMonth->daysInMonth))->toDateString(),
                'is_estimated' => true,
            ]);
        }

        return $cycles->where('closing_date', '>=', $purchaseDate)
            ->sortBy('closing_date')->take($count)->values()->all();
    }
}
