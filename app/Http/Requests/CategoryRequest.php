<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    private $user_id;

    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        $this->user_id = auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:categories,name,NULL,id,user_id,' . $this->user_id . ',type,' . $this->input('type')
            ],
            'type' => 'required|in:'.implode(',', TransactionType::values()),
        ];
    }

    protected function passedValidation()
    {
        $this->merge([
            'user_id' => $this->user_id,
        ]);
    }

}
