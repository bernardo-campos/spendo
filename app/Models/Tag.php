<?php

namespace App\Models;

use App\Traits\HasCurrentUserScope;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasCurrentUserScope;

    protected $fillable = [
        'user_id',
        'name',
        'type',
    ];

    protected $casts = [
        'type' => \App\Enums\TransactionType::class,
    ];
}
