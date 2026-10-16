<?php

namespace App\Http\Requests;

use App\Rules\UniqueCardBillingCycleMonth;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        $cardId = (int) $this->route('card')?->id;

        return [
            'closing_date' => [
                'required',
                'date_format:Y-m-d',
                new UniqueCardBillingCycleMonth($cardId),
            ],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:closing_date'],
        ];
    }
}
