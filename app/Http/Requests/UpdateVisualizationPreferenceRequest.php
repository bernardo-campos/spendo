<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVisualizationPreferenceRequest extends FormRequest
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
            'expense_list' => [
                'required',
                'array:show_category,show_description,show_cash_payment_method,show_credit_payment_method,show_tags,show_notes',
            ],
            'expense_list.show_category' => ['required', 'boolean'],
            'expense_list.show_description' => ['required', 'boolean'],
            'expense_list.show_cash_payment_method' => ['required', 'boolean'],
            'expense_list.show_credit_payment_method' => ['required', 'boolean'],
            'expense_list.show_tags' => ['required', 'boolean'],
            'expense_list.show_notes' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('expense_list.show_category') && ! $this->boolean('expense_list.show_description')) {
                    $validator->errors()->add(
                        'expense_list',
                        'Debe mostrar al menos la categoría o la descripción.'
                    );
                }
            },
        ];
    }
}
