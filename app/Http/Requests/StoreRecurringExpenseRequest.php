<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use App\Enums\TransactionCurrency;
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
        }];
    }
}
