<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CurrencyExchange extends Model
{
    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Transaction::class, 'exchange_id')->where('type', 'expense');
    }

    public function income(): HasOne
    {
        return $this->hasOne(Transaction::class, 'exchange_id')->where('type', 'income');
    }
}
