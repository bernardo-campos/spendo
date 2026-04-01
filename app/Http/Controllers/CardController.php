<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
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
            ->orderBy('name')
            ->get();

        return response()->json($cards);
    }

    public function store(StoreCardRequest $request): JsonResponse
    {
        $card = Card::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($card, 201);
    }

    public function show(Request $request, Card $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);

        return response()->json($card->load(['billingCycles' => fn ($query) => $query->orderBy('closing_date')]));
    }

    public function update(UpdateCardRequest $request, Card $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);

        $card->update($request->validated());

        return response()->json($card->fresh()->load(['billingCycles' => fn ($query) => $query->orderBy('closing_date')]));
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
