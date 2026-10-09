<?php

namespace App\Services;

use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RecurringExpensePreviewService
{
    public function __construct(private RecurringExpenseNoteService $noteService) {}

    public function chargeDate(RecurringExpense $expense, CarbonImmutable $month): CarbonImmutable
    {
        return $month->startOfMonth()->setDay(min($expense->day_of_month, $month->daysInMonth));
    }

    public function appliesTo(RecurringExpense $expense, CarbonImmutable $month): bool
    {
        $chargeDate = $this->chargeDate($expense, $month)->toDateString();

        return $expense->is_active
            && $chargeDate >= $expense->starts_on->toDateString()
            && ($expense->ends_on === null || $chargeDate <= $expense->ends_on->toDateString());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forPeriod(int $userId, CarbonImmutable $month): Collection
    {
        $period = $month->startOfMonth()->toDateString();
        $expenses = RecurringExpense::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('starts_on', '<=', $month->endOfMonth()->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $period))
            ->with(['card', 'category'])
            ->get()
            ->filter(fn (RecurringExpense $expense): bool => $this->appliesTo($expense, $month));

        $occurrences = RecurringExpenseOccurrence::query()
            ->whereIn('recurring_expense_id', $expenses->pluck('id'))
            ->whereDate('period', $period)
            ->with('transaction')
            ->get()
            ->keyBy('recurring_expense_id');

        $numberedSeries = RecurringExpense::query()
            ->where('user_id', $userId)
            ->whereIn('series_key', $expenses->where('number_occurrences_in_notes', true)->pluck('series_key'))
            ->get()
            ->groupBy('series_key');

        $variableSeries = $expenses->where('amount_type', 'variable')->pluck('series_key')->filter();
        $latestAmounts = RecurringExpenseOccurrence::query()
            ->whereHas('recurringExpense', fn ($query) => $query->whereIn('series_key', $variableSeries))
            ->where('period', '<', $period)
            ->where('status', 'confirmed')
            ->whereHas('transaction')
            ->with(['transaction', 'recurringExpense'])
            ->orderByDesc('period')
            ->get()
            ->unique(fn (RecurringExpenseOccurrence $occurrence): string => $occurrence->recurringExpense->series_key)
            ->mapWithKeys(fn (RecurringExpenseOccurrence $occurrence): array => [
                $occurrence->recurringExpense->series_key => $occurrence->transaction->amount,
            ]);

        return $expenses->map(function (RecurringExpense $expense) use ($month, $occurrences, $latestAmounts, $numberedSeries): array {
            $occurrence = $occurrences->get($expense->id);
            $suggestedAmount = $expense->amount_type === 'variable'
                ? $latestAmounts->get($expense->series_key)
                : $expense->amount;

            return [
                'id' => $expense->id,
                'description' => $occurrence?->transaction?->description ?? $expense->description,
                'place' => $occurrence?->transaction?->place ?? $expense->place,
                'notes' => $occurrence?->transaction?->notes ?? $this->noteService->forMonth($expense, $month, $numberedSeries->get($expense->series_key)),
                'amount_type' => $expense->amount_type,
                'suggested_amount' => $suggestedAmount,
                'amount' => $occurrence?->transaction?->amount ?? $suggestedAmount,
                'currency' => $expense->currency,
                'charge_date' => $this->chargeDate($expense, $month)->toDateString(),
                'payment_method' => $expense->payment_method,
                'card' => $expense->card,
                'category' => $expense->category,
                'status' => $occurrence?->status ?? 'pending',
                'transaction_id' => $occurrence?->transaction_id,
            ];
        })->sortBy('charge_date')->values();
    }
}
