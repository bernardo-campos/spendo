<?php

namespace App\Rules;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTurnstile implements ValidationRule
{
    public function __construct(
        private TurnstileVerifier $verifier,
        private ?string $remoteIp,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->verifier->verify($value, $this->remoteIp)) {
            $fail('No pudimos verificar que seas una persona. Intentá nuevamente.');
        }
    }
}
