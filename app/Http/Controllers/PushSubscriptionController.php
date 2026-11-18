<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Services\WebPushConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    public function index(Request $request, WebPushConfiguration $configuration): JsonResponse
    {
        $configured = $configuration->isConfigured();

        return response()->json([
            'configured' => $configured,
            'public_key' => $configured ? config('webpush.vapid.public_key') : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StorePushSubscriptionRequest $request, WebPushConfiguration $configuration): JsonResponse
    {
        abort_unless($configuration->isConfigured(), 503, 'Las notificaciones todavía no están habilitadas en Spendo.');

        $data = $request->validated();

        DB::transaction(function () use ($request, $data): void {
            $existing = PushSubscription::query()->where('endpoint', $data['endpoint'])->lockForUpdate()->first();

            abort_if($existing !== null && ! $request->user()->ownsPushSubscription($existing), 409,
                'Esta suscripción pertenece a otra cuenta. Desactivá las notificaciones y volvé a activarlas.');

            $request->user()->updatePushSubscription(
                $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], 'aes128gcm',
            );
        });

        return response()->json(status: 204);
    }

    public function destroy(DeletePushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->deletePushSubscription($request->validated('endpoint'));

        return response()->json(status: 204);
    }
}
