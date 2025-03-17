<?php

namespace App\Models;

use App\Traits\HasCurrentUserScope;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasCurrentUserScope;

    protected $fillable = [
        'user_id',
        'category_id',
        'currency_id',
        'amount',
        'date',
        'description',
        'type',
    ];

    protected $casts = [
        'type' => \App\Enums\TransactionType::class,
    ];

    // Scopes opcionales para facilitar consultas
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    /* ----- Relaciones ----- */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'transaction_tags');
    }
}
