<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasCurrentUserScope
{
    public function scopeCurrentUser(Builder $query): Builder
    {
        return $query->where('user_id', auth()->id());
    }
}