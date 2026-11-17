<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchTransactionRequest extends FormRequest
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
            'type' => ['required', Rule::in(['income', 'expense'])],
            'date_scope' => ['required', Rule::in(['period', 'range', 'all'])],
            'period' => ['exclude_unless:date_scope,period', 'required', 'date_format:Y-m'],
            'date_from' => ['exclude_unless:date_scope,range', 'required', 'date_format:Y-m-d'],
            'date_to' => ['exclude_unless:date_scope,range', 'required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'description' => ['nullable', 'string', 'max:255'],
            'place' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('amount_min'), ['gte:amount_min'])],
            'currency' => ['nullable', Rule::in(['ARS', 'USD'])],
            'payment_method' => ['exclude_unless:type,expense', 'nullable', Rule::in(['cash', 'credit'])],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'card_id' => [
                'exclude_unless:type,expense', 'nullable', 'integer',
                Rule::exists('cards', 'id')->where('user_id', $this->user()->id),
            ],
            'tag_ids' => ['sometimes', 'array', 'max:100'],
            'tag_ids.*' => [
                'integer', 'distinct',
                Rule::exists('tags', 'id')->where('user_id', $this->user()->id),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'La fecha Hasta debe ser posterior o igual a Desde.',
            'amount_max.gte' => 'El monto máximo debe ser mayor o igual al mínimo.',
        ];
    }
}
