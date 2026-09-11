<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, bool>
     */
    public const EXPENSE_LIST_DISPLAY_DEFAULTS = [
        'show_category' => true,
        'show_description' => true,
        'show_cash_payment_method' => false,
        'show_credit_payment_method' => true,
        'show_tags' => true,
        'show_notes' => false,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'expense_list_display_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'expense_list_display_preferences' => 'array',
            'password' => 'hashed',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function expenseListDisplayPreferences(): array
    {
        return [
            ...self::EXPENSE_LIST_DISPLAY_DEFAULTS,
            ...($this->expense_list_display_preferences ?? []),
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }
}
