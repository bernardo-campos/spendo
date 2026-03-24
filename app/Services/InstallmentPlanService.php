<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use Carbon\CarbonImmutable;

class InstallmentPlanService
{
    /**
     * @return list<array{installment_number:int, amount:string, due_date:string, status:string}>
     */
    public function buildInstallments(
        string $totalAmount,
        int $installmentsCount,
        string $firstDueDate,
    ): array {
        $totalCents = (int) round((float) $totalAmount * 100);
        $baseCents = intdiv($totalCents, $installmentsCount);
        $remainingCents = $totalCents;
        $items = [];
        $date = CarbonImmutable::parse($firstDueDate);

        for ($index = 1; $index <= $installmentsCount; $index++) {
            $amountCents = $baseCents;

            if ($index === $installmentsCount) {
                $amountCents = $remainingCents;
            }

            $items[] = [
                'installment_number' => $index,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'due_date' => $date->addMonthsNoOverflow($index - 1)->toDateString(),
                'status' => 'pending',
            ];

            $remainingCents -= $amountCents;
        }

        return $items;
    }

    public function syncStatus(InstallmentPlan $plan): void
    {
        $pendingExists = $plan->installments()->where('status', 'pending')->exists();

        $plan->update([
            'status' => $pendingExists ? 'pending' : 'completed',
        ]);
    }
}
