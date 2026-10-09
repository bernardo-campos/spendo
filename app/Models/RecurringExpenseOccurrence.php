<?php

namespace App\Models;

use Database\Factories\RecurringExpenseOccurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpenseOccurrence extends Model
{
    /** @use HasFactory<RecurringExpenseOccurrenceFactory> */
    use HasFactory;

    protected $fillable = ['recurring_expense_id', 'transaction_id', 'period', 'status'];

    protected function casts(): array
    {
        return ['period' => 'date'];
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
