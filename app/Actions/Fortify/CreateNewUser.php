<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\ValidTurnstile;
use App\Services\TurnstileVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private TurnstileVerifier $turnstileVerifier) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'cf-turnstile-response' => [
                'bail',
                'required',
                'string',
                'max:2048',
                new ValidTurnstile($this->turnstileVerifier, request()->ip()),
            ],
        ], [
            'cf-turnstile-response.required' => 'No pudimos verificar que seas una persona. Intentá nuevamente.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
