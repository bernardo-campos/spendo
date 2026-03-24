<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstallmentPlanRequest;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Services\InstallmentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentPlanController extends Controller
{
    public function __construct(private InstallmentPlanService $installmentPlanService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $plans = InstallmentPlan::query()
            ->where('user_id', $user->id)
            ->with(['transaction', 'card', 'installments'])
            ->latest()
            ->get();

        return response()->json($plans);
    }

    public function store(StoreInstallmentPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $plan = DB::transaction(function () use ($user, $validated) {
            $plan = InstallmentPlan::query()->create([
                'user_id' => $user->id,
                'transaction_id' => $validated['transaction_id'],
                'card_id' => $validated['card_id'],
                'installments_count' => $validated['installments_count'],
                'total_amount' => (string) $user->transactions()->findOrFail($validated['transaction_id'])->amount,
                'first_due_date' => $validated['first_due_date'],
                'status' => 'pending',
            ]);

            $plan->installments()->createMany(
                $this->installmentPlanService->buildInstallments(
                    (string) $plan->total_amount,
                    (int) $plan->installments_count,
                    $plan->first_due_date->toDateString(),
                )
            );

            return $plan;
        });

        return response()->json($plan->load(['transaction', 'card', 'installments']), 201);
    }

    public function show(Request $request, InstallmentPlan $installmentPlan): JsonResponse
    {
        abort_unless($installmentPlan->user_id === $request->user()?->id, 404);

        return response()->json($installmentPlan->load(['transaction', 'card', 'installments']));
    }

    public function update(Request $request, InstallmentPlan $installmentPlan): JsonResponse
    {
        abort_unless($installmentPlan->user_id === $request->user()?->id, 404);

        $validated = $request->validate([
            'installment_id' => ['required', 'integer'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $installment = Installment::query()
            ->where('installment_plan_id', $installmentPlan->id)
            ->where('id', $validated['installment_id'])
            ->firstOrFail();

        $installment->update([
            'paid_at' => $validated['paid_at'] ?? now()->toDateString(),
            'status' => 'paid',
        ]);

        $this->installmentPlanService->syncStatus($installmentPlan->fresh('installments'));

        return response()->json($installmentPlan->fresh()->load(['transaction', 'card', 'installments']));
    }

    public function destroy(Request $request, InstallmentPlan $installmentPlan): JsonResponse
    {
        abort_unless($installmentPlan->user_id === $request->user()?->id, 404);

        $installmentPlan->delete();

        return response()->json(status: 204);
    }
}
