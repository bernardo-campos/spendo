<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
            ],
            'card_id' => [
                'nullable',
                Rule::exists('cards', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'type' => ['sometimes', Rule::in(['income', 'expense'])],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'purchase_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
