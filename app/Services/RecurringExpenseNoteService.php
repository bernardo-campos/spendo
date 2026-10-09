<?php

namespace App\Services;

use App\Models\RecurringExpense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RecurringExpenseNoteService
{
    /**
     * @param  Collection<int, RecurringExpense>|null  $series
     */
    public function forMonth(RecurringExpense $expense, CarbonImmutable $month, ?Collection $series = null): ?string
    {
        $baseNote = trim($expense->notes ?? '');

        if (! $expense->number_occurrences_in_notes) {
            return $baseNote !== '' ? $baseNote : null;
        }

        $series ??= $expense->series_key === null
            ? collect([$expense])
            : RecurringExpense::query()
                ->where('user_id', $expense->user_id)
                ->where('series_key', $expense->series_key)
                ->get();

        $total = 0;
        $position = 0;
        $targetMonth = $this->monthIndex($month);

        foreach ($series as $version) {
            if (! $version->is_active || $version->ends_on === null) {
                continue;
            }

            $firstMonth = CarbonImmutable::parse($version->starts_on->toDateString())->startOfMonth();
            if ($this->chargeDate($version, $firstMonth)->lt($version->starts_on)) {
                $firstMonth = $firstMonth->addMonth();
            }

            $lastMonth = CarbonImmutable::parse($version->ends_on->toDateString())->startOfMonth();
            if ($this->chargeDate($version, $lastMonth)->gt($version->ends_on)) {
                $lastMonth = $lastMonth->subMonth();
            }

            $firstIndex = $this->monthIndex($firstMonth);
            $lastIndex = $this->monthIndex($lastMonth);
            if ($firstIndex > $lastIndex) {
                continue;
            }

            $total += $lastIndex - $firstIndex + 1;
            if ($targetMonth >= $firstIndex) {
                $position += min($targetMonth, $lastIndex) - $firstIndex + 1;
            }
        }

        if ($total === 0 || $position === 0 || $position > $total) {
            return $baseNote !== '' ? $baseNote : null;
        }

        $numbering = "{$position} de {$total}";

        return $baseNote !== '' ? "{$baseNote}\n{$numbering}" : $numbering;
    }

    private function chargeDate(RecurringExpense $expense, CarbonImmutable $month): CarbonImmutable
    {
        return $month->setDay(min($expense->day_of_month, $month->daysInMonth));
    }

    private function monthIndex(CarbonImmutable $month): int
    {
        return ($month->year * 12) + $month->month;
    }
}
