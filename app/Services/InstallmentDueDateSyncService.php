<?php

namespace App\Services;

use App\Enums\PaymentMethodType;
use App\Models\Card;
use App\Models\InstallmentPlan;

class InstallmentDueDateSyncService
{
    public function __construct(private CardPaymentDateService $cardPaymentDateService) {}

    public function syncCard(Card $card): void
    {
        $card->load('billingCycles');

        $plans = $card->installmentPlans()
            ->where('status', 'pending')
            ->with(['transaction', 'installments'])
            ->get();

        foreach ($plans as $plan) {
            $this->syncPlan($plan);
        }

        $card->transactions()
            ->where('type', 'expense')
            ->where('payment_method', PaymentMethodType::Credit->value)
            ->whereDoesntHave('installmentPlan')
            ->chunkById(100, function ($transactions) use ($card): void {
                foreach ($transactions as $transaction) {
                    $paymentDate = $this->cardPaymentDateService->resolve(
                        $transaction->purchase_date->toDateString(),
                        PaymentMethodType::Credit->value,
                        $card,
                    )['date'];

                    if ($transaction->payment_date?->toDateString() !== $paymentDate) {
                        $transaction->update(['payment_date' => $paymentDate]);
                    }
                }
            });
    }

    public function syncPlan(InstallmentPlan $plan): void
    {
        $transaction = $plan->transaction;

        if ($transaction === null) {
            return;
        }

        $card = $plan->card()->with('billingCycles')->first();

        if ($card === null) {
            return;
        }

        $closingDay = $card->closing_day ?? 1;
        $statementMonth = $this->cardPaymentDateService->statementMonthForPurchase(
            $transaction->purchase_date->toImmutable(),
            $card,
        );
        $cyclesByMonth = $card->billingCycles
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
            $dueDay = $card->due_day ?? $closingDay;
            $dueMonth = $closingDay === $dueDay
                ? $statementMonth->addMonthsNoOverflow($index)
                : $statementMonth->addMonthsNoOverflow($index + 1);
            $dueDay = min($dueDay, $dueMonth->endOfMonth()->day);

            $installment->update([
                'due_date' => $cycle?->due_date->toDateString()
                    ?? $dueMonth->setDay($dueDay)->toDateString(),
                'due_date_is_estimated' => $cycle === null,
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
