<?php

namespace App\Models;

use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use HasFactory;

    protected $attributes = [
        'currency' => 'ARS',
        'is_active' => true,
    ];

    protected $fillable = [
        'user_id', 'category_id', 'card_id', 'description', 'place',
        'payment_method', 'currency', 'amount_type', 'amount',
        'day_of_month', 'starts_on', 'ends_on', 'is_active', 'series_key',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $expense): void {
            $expense->series_key ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day_of_month' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringExpenseOccurrence::class);
    }
}
