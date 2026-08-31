<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TurnstileVerifier
{
    public function verify(string $token, ?string $remoteIp): bool
    {
        $secretKey = config('services.turnstile.secret_key');
        $expectedHostname = config('services.turnstile.expected_hostname');

        if (! is_string($secretKey) || $secretKey === '' || ! is_string($expectedHostname) || $expectedHostname === '') {
            Log::warning('Turnstile validation failed because its configuration is incomplete.');

            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(2)
                ->timeout(5)
                ->retry([100, 300], 0, $this->shouldRetry(...), throw: false)
                ->post(config('services.turnstile.verify_url'), [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                    'idempotency_key' => (string) Str::uuid(),
                ]);
        } catch (Throwable $exception) {
            Log::warning('Turnstile validation request failed.', [
                'exception' => $exception::class,
            ]);

            return false;
        }

        $result = $response->json();

        if (! $response->successful() || ! is_array($result) || ($result['success'] ?? false) !== true) {
            Log::warning('Turnstile rejected a registration challenge.', [
                'status' => $response->status(),
                'error_codes' => is_array($result) ? ($result['error-codes'] ?? []) : [],
            ]);

            return false;
        }

        if (($result['action'] ?? null) !== config('services.turnstile.action')) {
            Log::warning('Turnstile returned an unexpected action.');

            return false;
        }

        $hostname = $result['hostname'] ?? null;

        if (! is_string($hostname) || strcasecmp($hostname, $expectedHostname) !== 0) {
            Log::warning('Turnstile returned an unexpected hostname.', [
                'hostname' => is_string($hostname) ? $hostname : null,
            ]);

            return false;
        }

        return true;
    }

    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && $exception->response->serverError());
    }
}
