<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardBillingCycleRequest;
use App\Http\Requests\UpdateCardBillingCycleRequest;
use App\Models\Card;
use App\Models\CardBillingCycle;
use App\Services\InstallmentDueDateSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardBillingCycleController extends Controller
{
    public function __construct(private InstallmentDueDateSyncService $installmentDueDateSyncService) {}

    public function index(Request $request, Card $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);

        return response()->json(
            $card->billingCycles()->orderBy('closing_date')->get()
        );
    }

    public function store(StoreCardBillingCycleRequest $request, Card $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);

        $cycle = $card->billingCycles()->create($request->validated());

        $this->installmentDueDateSyncService->syncCard($card->fresh());

        return response()->json($cycle->fresh(), 201);
    }

    public function show(Request $request, Card $card, CardBillingCycle $billingCycle): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);
        abort_unless($billingCycle->card_id === $card->id, 404);

        return response()->json($billingCycle);
    }

    public function update(
        UpdateCardBillingCycleRequest $request,
        Card $card,
        CardBillingCycle $billingCycle,
    ): JsonResponse {
        abort_unless($card->user_id === $request->user()?->id, 404);
        abort_unless($billingCycle->card_id === $card->id, 404);

        $validated = $request->validated();

        if (isset($validated['due_date']) && ! isset($validated['closing_date'])) {
            $validated['closing_date'] = $billingCycle->closing_date->toDateString();
        }

        if (isset($validated['closing_date']) && ! isset($validated['due_date'])) {
            $validated['due_date'] = $billingCycle->due_date->toDateString();
        }

        abort_if(
            isset($validated['due_date'], $validated['closing_date'])
            && $validated['due_date'] <= $validated['closing_date'],
            422,
            'La fecha de vencimiento debe ser posterior al cierre.'
        );

        $billingCycle->update($validated);

        $this->installmentDueDateSyncService->syncCard($card->fresh());

        return response()->json($billingCycle->fresh());
    }

    public function destroy(Request $request, Card $card, CardBillingCycle $billingCycle): JsonResponse
    {
        abort_unless($card->user_id === $request->user()?->id, 404);
        abort_unless($billingCycle->card_id === $card->id, 404);

        $billingCycle->delete();

        $this->installmentDueDateSyncService->syncCard($card->fresh());

        return response()->json(status: 204);
    }
}
