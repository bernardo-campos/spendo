<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardBillingCycleRequest extends FormRequest
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

        return [
            'closing_date' => [
                'required',
                'date',
                Rule::unique('card_billing_cycles', 'closing_date')->where(
                    fn ($query) => $query->where('card_id', $cardId)
                ),
            ],
            'due_date' => ['required', 'date', 'after:closing_date'],
        ];
    }
}
