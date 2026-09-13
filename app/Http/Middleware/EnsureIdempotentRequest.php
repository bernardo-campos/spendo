<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $next($request);
        }

        if (mb_strlen($key) > 128) {
            return response()->json(['message' => 'La clave de idempotencia es inválida.'], 422);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $requestHash = hash('sha256', $request->getContent());
        $attributes = [
            'user_id' => $user->id,
            'key' => $key,
        ];

        return Cache::lock('idempotency:'.$user->id.':'.hash('sha256', $key), 10)->block(5, function () use ($attributes, $next, $request, $requestHash): Response {
            $existing = IdempotencyKey::query()->where($attributes)->first();

            if ($existing !== null) {
                return $this->replayOrReject($existing, $request, $requestHash);
            }

            $response = $next($request);
            $json = $this->jsonBody($response);

            IdempotencyKey::query()->create([
                ...$attributes,
                'method' => $request->method(),
                'path' => $request->getPathInfo(),
                'request_hash' => $requestHash,
                'response_status' => $response->getStatusCode(),
                'response_body' => $json,
            ]);

            return $response;
        });
    }

    private function replayOrReject(IdempotencyKey $existing, Request $request, string $requestHash): JsonResponse
    {
        if (
            $existing->method !== $request->method()
            || $existing->path !== $request->getPathInfo()
            || $existing->request_hash !== $requestHash
        ) {
            return response()->json(['message' => 'La clave de idempotencia ya se usó con otra solicitud.'], 422);
        }

        if ($existing->response_status === 204) {
            return response()->json(status: 204);
        }

        return response()->json($existing->response_body, $existing->response_status);
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private function jsonBody(Response $response): ?array
    {
        if ($response->getContent() === '') {
            return null;
        }

        $decoded = json_decode($response->getContent(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
