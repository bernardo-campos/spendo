<?php

namespace Database\Factories;

use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => fake()->company(),
            'payment_method' => 'cash',
            'currency' => 'ARS',
            'amount_type' => 'fixed',
            'amount' => '1000.00',
            'day_of_month' => 10,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ];
    }
}
