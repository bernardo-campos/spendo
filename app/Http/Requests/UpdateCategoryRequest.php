<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id ?? $this->route('category');

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('categories', 'slug')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($categoryId),
            ],
            'scope' => ['sometimes', Rule::in(['income', 'expense', 'both'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
