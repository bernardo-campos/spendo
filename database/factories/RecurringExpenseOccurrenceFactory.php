<?php

namespace Database\Factories;

use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpenseOccurrence>
 */
class RecurringExpenseOccurrenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recurring_expense_id' => RecurringExpense::factory(),
            'period' => now()->startOfMonth()->toDateString(),
            'status' => 'skipped',
        ];
    }
}
