<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstallmentPlanRequest extends FormRequest
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
            'transaction_id' => [
                'required',
                Rule::exists('transactions', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'card_id' => [
                'required',
                Rule::exists('cards', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'installments_count' => ['required', 'integer', 'min:2', 'max:120'],
            'first_due_date' => ['required', 'date'],
        ];
    }
}
