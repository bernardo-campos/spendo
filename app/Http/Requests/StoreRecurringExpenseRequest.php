<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use App\Enums\TransactionCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRecurringExpenseRequest extends FormRequest
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
        return [
            'description' => ['required', 'string', 'max:255'],
            'place' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', $this->user()->id)->whereIn('scope', ['expense', 'both'])],
            'payment_method' => ['required', Rule::in([PaymentMethodType::Cash->value, PaymentMethodType::Credit->value])],
            'card_id' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('payment_method') === PaymentMethodType::Credit->value), Rule::exists('cards', 'id')->where('user_id', $this->user()->id)],
            'currency' => ['required', Rule::enum(TransactionCurrency::class)],
            'amount_type' => ['required', Rule::in(['fixed', 'variable'])],
            'amount' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('amount_type') === 'fixed'), 'numeric', 'gt:0'],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:4800'],
            'number_occurrences_in_notes' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('amount_type') === 'variable' && $this->filled('amount')) {
                $validator->errors()->add('amount', 'Un gasto variable no tiene un importe fijo.');
            }

            if ($this->input('payment_method') !== PaymentMethodType::Credit->value && $this->filled('card_id')) {
                $validator->errors()->add('card_id', 'La tarjeta solo corresponde a pagos con crédito.');
            }

            if ($this->boolean('number_occurrences_in_notes') && ! $this->filled('ends_on')) {
                $validator->errors()->add('ends_on', 'Indique una fecha de fin para numerar las repeticiones.');
            }

            if ($this->filled('ends_on') && $validator->errors()->isEmpty()) {
                $start = CarbonImmutable::parse($this->input('starts_on'));
                $end = CarbonImmutable::parse($this->input('ends_on'));
                $firstMonth = $start->startOfMonth();
                $chargeDate = $firstMonth->setDay(min((int) $this->input('day_of_month'), $firstMonth->daysInMonth));

                if ($chargeDate->lt($start)) {
                    $nextMonth = $firstMonth->addMonth();
                    $chargeDate = $nextMonth->setDay(min((int) $this->input('day_of_month'), $nextMonth->daysInMonth));
                }

                if ($chargeDate->gt($end)) {
                    $validator->errors()->add('ends_on', 'El rango elegido no contiene ningún cargo mensual.');
                }
            }
        }];
    }
}
