<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'payment_method' => [
                'sometimes',
                Rule::in([
                    PaymentMethodType::Cash->value,
                    PaymentMethodType::Credit->value,
                ]),
                Rule::requiredIf(fn () => $this->input('type') === 'expense'),
            ],
            'card_id' => [
                'nullable',
                Rule::exists('cards', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                Rule::requiredIf(fn () => $this->input('type') === 'expense' && $this->input('payment_method') === PaymentMethodType::Credit->value),
            ],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'purchase_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'installments_count' => [
                'nullable',
                'integer',
                'min:1',
                'max:120',
                Rule::requiredIf(fn () => $this->input('type') === 'expense' && $this->input('payment_method') === PaymentMethodType::Credit->value),
            ],
        ];
    }
}
