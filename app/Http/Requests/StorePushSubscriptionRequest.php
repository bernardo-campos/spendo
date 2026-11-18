<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StorePushSubscriptionRequest extends FormRequest
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
            'endpoint' => ['required', 'string', 'url:https', 'max:1024'],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'regex:/^B[A-Za-z0-9_-]{86}$/D'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}$/D'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('endpoint')) {
                return;
            }

            $url = parse_url($this->string('endpoint')->toString());

            if (! is_array($url)
                || ! Str::is(config('webpush.allowed_hosts', []), strtolower($url['host'] ?? ''))
                || isset($url['user'])
                || isset($url['fragment'])
                || (isset($url['port']) && $url['port'] !== 443)) {
                $validator->errors()->add('endpoint', 'El servicio de notificaciones no está permitido.');
            }
        }];
    }
}
