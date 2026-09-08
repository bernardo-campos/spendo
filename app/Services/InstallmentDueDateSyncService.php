<?php

namespace App\Services;

use App\Models\Card;
use App\Models\InstallmentPlan;
use Carbon\CarbonImmutable;

class InstallmentDueDateSyncService
{
    public function syncCard(Card $card): void
    {
        $plans = $card->installmentPlans()
            ->where('status', 'pending')
            ->with(['transaction', 'installments'])
            ->get();

        foreach ($plans as $plan) {
            $this->syncPlan($plan);
        }
    }

    public function syncPlan(InstallmentPlan $plan): void
    {
        $transaction = $plan->transaction;

        if ($transaction === null) {
            return;
        }

        $card = $plan->card()->first();

        if ($card === null) {
            return;
        }

        $closingDay = $card->closing_day ?? 1;
        $purchaseDate = CarbonImmutable::instance($transaction->purchase_date);
        $statementMonth = $purchaseDate->day <= $closingDay
            ? $purchaseDate->startOfMonth()
            : $purchaseDate->addMonthNoOverflow()->startOfMonth();
        $cyclesByMonth = $card->billingCycles()
            ->get()
            ->keyBy(fn ($cycle): string => $cycle->closing_date->format('Y-m'));

        $installments = $plan->installments()
            ->orderBy('installment_number')
            ->get()
            ->values();

        foreach ($installments as $index => $installment) {
            if ($installment->status !== 'pending') {
                continue;
            }

            $cycleMonth = $statementMonth->addMonthsNoOverflow($index)->format('Y-m');
            $cycle = $cyclesByMonth->get($cycleMonth);

            if ($cycle === null) {
                continue;
            }

            $installment->update([
                'due_date' => $cycle->due_date->toDateString(),
                'due_date_is_estimated' => false,
            ]);
        }

        $firstInstallment = $plan->installments()
            ->orderBy('installment_number')
            ->first();

        if ($firstInstallment !== null) {
            $firstDueDate = $firstInstallment->due_date->toDateString();

            if ($plan->first_due_date->toDateString() !== $firstDueDate) {
                $plan->update(['first_due_date' => $firstDueDate]);
            }

            if ($transaction->payment_date?->toDateString() !== $firstDueDate) {
                $transaction->update(['payment_date' => $firstDueDate]);
            }
        }
    }
}
