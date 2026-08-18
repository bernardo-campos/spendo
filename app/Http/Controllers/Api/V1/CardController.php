<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\Api\V1\CardResource;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $cards = Card::query()
            ->where('user_id', $user->id)
            ->with(['billingCycles' => fn ($query) => $query->orderBy('closing_date')])
            ->orderBy('name');

        if ($request->routeIs('api.v1.*')) {
            return CardResource::collection($cards->paginate())->response();
        }

        return response()->json($cards->get());
    }

    public function store(StoreCardRequest $request): JsonResponse
    {
        $card = Card::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        if ($request->routeIs('api.v1.*')) {
            return (new CardResource($card))->response()->setStatusCode(201);
        }

        return response()->json($card, 201);
    }

    public function show(Request $request, Card $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);

        $card->load(['billingCycles' => fn ($query) => $query->orderBy('closing_date')]);

        if ($request->routeIs('api.v1.*')) {
            return (new CardResource($card))->response();
        }

        return response()->json($card);
    }

    public function update(UpdateCardRequest $request, Card $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);

        $card->update($request->validated());

        $card = $card->fresh()->load(['billingCycles' => fn ($query) => $query->orderBy('closing_date')]);

        if ($request->routeIs('api.v1.*')) {
            return (new CardResource($card))->response();
        }

        return response()->json($card);
    }

    public function destroy(Request $request, Card $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);

        abort_if(
            $card->transactions()->exists(),
            422,
            'No se puede eliminar una tarjeta asociada a transacciones.'
        );

        $card->delete();

        return response()->json(status: 204);
    }
}
