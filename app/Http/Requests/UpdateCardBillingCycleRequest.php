<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardBillingCycleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cardId = $this->route('card')?->id;
        $cycleId = $this->route('billingCycle')?->id;

        return [
            'closing_date' => [
                'sometimes',
                'date',
                Rule::unique('card_billing_cycles', 'closing_date')
                    ->where(fn ($query) => $query->where('card_id', $cardId))
                    ->ignore($cycleId),
            ],
            'due_date' => ['sometimes', 'date'],
        ];
    }
}
