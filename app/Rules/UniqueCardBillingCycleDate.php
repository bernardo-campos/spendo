<?php

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueCardBillingCycleDate implements ValidationRule
{
    public function __construct(private int $cardId, private ?int $exceptCycleId = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $closingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($closingDate === false || $closingDate->format('Y-m-d') !== $value) {
            return;
        }

        $query = DB::table('card_billing_cycles')
            ->where('card_id', $this->cardId)
            ->where('closing_date', $closingDate->format('Y-m-d'));

        if ($this->exceptCycleId !== null) {
            $query->where('id', '!=', $this->exceptCycleId);
        }

        if ($query->exists()) {
            $fail('Ya existe un ciclo de facturación con esa fecha de cierre en esta tarjeta.');
        }
    }
}
