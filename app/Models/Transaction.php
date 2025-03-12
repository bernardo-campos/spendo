<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'currency_id',
        'amount',
        'date',
        'description',
        'type',
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

    public function scopeCurrentUser($query)
    {
        return $query->where('user_id', auth()->id());
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
