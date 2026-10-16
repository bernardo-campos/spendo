<?php

namespace App\Http\Requests;

use App\Rules\UniqueCardBillingCycleMonth;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        $cardId = (int) $this->route('card')?->id;
        $cycle = $this->route('billingCycle');
        $closingDateRules = ['sometimes', 'date_format:Y-m-d'];

        if ($this->input('closing_date') !== $cycle->closing_date->toDateString()) {
            $closingDateRules[] = new UniqueCardBillingCycleMonth($cardId, (int) $cycle->id);
        }

        return [
            'closing_date' => $closingDateRules,
            'due_date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cycle = $this->route('billingCycle');
                $closingDate = $this->input('closing_date', $cycle->closing_date->toDateString());
                $dueDate = $this->input('due_date', $cycle->due_date->toDateString());

                if ($dueDate < $closingDate) {
                    $validator->errors()->add('due_date', 'La fecha de vencimiento debe ser igual o posterior al cierre.');
                }
            },
        ];
    }
}
