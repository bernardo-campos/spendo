<?php

namespace App\Http\Requests;

use App\Enums\TransactionCurrency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrencyExchangeRequest extends FormRequest
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
            'source_currency' => ['required', Rule::enum(TransactionCurrency::class)],
            'source_amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'target_currency' => ['required', Rule::enum(TransactionCurrency::class), 'different:source_currency'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'purchase_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'place' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->whereIn('scope', ['expense', 'both'])),
            ],
            'income_category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->whereIn('scope', ['income', 'both'])),
            ],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
        ];
    }
}
