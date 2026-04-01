<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethodType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Card;
use App\Models\InstallmentPlan;
use App\Models\Transaction;
use App\Services\CardPaymentDateService;
use App\Services\InstallmentDueDateSyncService;
use App\Services\InstallmentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function __construct(
        private InstallmentPlanService $installmentPlanService,
        private CardPaymentDateService $cardPaymentDateService,
        private InstallmentDueDateSyncService $installmentDueDateSyncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->with(['category', 'card', 'tags', 'installmentPlan.installments'])
            ->latest('purchase_date')
            ->get();

        return response()->json($transactions);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($user, $validated) {
            $paymentMethod = null;
            $card = null;
            $installmentsCount = 1;

            if ($validated['type'] === 'expense') {
                $paymentMethod = $validated['payment_method'] ?? PaymentMethodType::Cash->value;

                if ($paymentMethod === PaymentMethodType::Credit->value) {
                    $cardId = $validated['card_id'] ?? null;
                    $installmentsCount = (int) ($validated['installments_count'] ?? 1);

                    abort_if($cardId === null, 422, 'Debe seleccionar una tarjeta para esta forma de pago.');

                    $card = Card::query()
                        ->where('user_id', $user->id)
                        ->where('id', $cardId)
                        ->firstOrFail();
                }
            }

            $paymentDate = $validated['purchase_date'];

            if ($validated['type'] === 'expense') {
                $resolvedPaymentDate = $this->cardPaymentDateService->resolve(
                    $validated['purchase_date'],
                    $paymentMethod ?? PaymentMethodType::Cash->value,
                    $card,
                );

                $paymentDate = $resolvedPaymentDate['date'];
            }

            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'] ?? null,
                'payment_method' => $paymentMethod,
                'card_id' => $card?->id,
                'type' => $validated['type'],
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'purchase_date' => $validated['purchase_date'],
                'payment_date' => $paymentDate,
                'notes' => $validated['notes'] ?? null,
            ]);

            if (isset($validated['tag_ids']) && is_array($validated['tag_ids'])) {
                $transaction->tags()->sync($validated['tag_ids']);
            }

            if (
                $transaction->type === 'expense'
                && $transaction->payment_method === PaymentMethodType::Credit->value
                && $installmentsCount > 1
                && $card !== null
            ) {
                $plan = InstallmentPlan::query()->create([
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'card_id' => $card->id,
                    'installments_count' => $installmentsCount,
                    'total_amount' => $transaction->amount,
                    'first_due_date' => $transaction->payment_date,
                    'status' => 'pending',
                ]);

                $plan->installments()->createMany(
                    $this->installmentPlanService->buildInstallments(
                        (string) $transaction->amount,
                        $installmentsCount,
                        $transaction->payment_date->toDateString(),
                    )
                );

                $this->installmentDueDateSyncService->syncPlan($plan->fresh(['transaction', 'installments']));
            }

            return $transaction;
        });

        return response()->json(
            $transaction->load(['category', 'card', 'tags', 'installmentPlan.installments']),
            201
        );
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        abort_unless($transaction->user_id === $request->user()?->id, 404);

        return response()->json($transaction->load(['category', 'card', 'tags', 'installmentPlan.installments']));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        abort_unless($transaction->user_id === $request->user()?->id, 404);

        $validated = $request->validated();
        $effectivePaymentMethod = $validated['payment_method'] ?? $transaction->payment_method;

        if ($effectivePaymentMethod === PaymentMethodType::Cash->value) {
            $validated['card_id'] = null;
        }

        if ($effectivePaymentMethod === PaymentMethodType::Credit->value) {
            $cardId = $validated['card_id'] ?? $transaction->card_id;

            abort_if($cardId === null, 422, 'Debe seleccionar una tarjeta para pago con crédito.');

            $card = Card::query()
                ->where('user_id', $request->user()->id)
                ->where('id', $cardId)
                ->firstOrFail();

            if (isset($validated['purchase_date']) || isset($validated['payment_method']) || isset($validated['card_id'])) {
                $resolvedPaymentDate = $this->cardPaymentDateService->resolve(
                    $validated['purchase_date'] ?? $transaction->purchase_date->toDateString(),
                    PaymentMethodType::Credit->value,
                    $card,
                );

                $validated['payment_date'] = $resolvedPaymentDate['date'];
            }
        }

        if ($effectivePaymentMethod === PaymentMethodType::Cash->value && isset($validated['purchase_date'])) {
            $validated['payment_date'] = $validated['purchase_date'];
        }

        $transaction->update($validated);

        if ($transaction->payment_method === PaymentMethodType::Credit->value && $transaction->installmentPlan !== null) {
            $this->installmentDueDateSyncService->syncPlan($transaction->installmentPlan->fresh(['transaction', 'installments']));
        }

        if ($request->has('tag_ids')) {
            $transaction->tags()->sync($request->validated('tag_ids', []));
        }

        return response()->json($transaction->fresh()->load(['category', 'card', 'tags', 'installmentPlan.installments']));
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        abort_unless($transaction->user_id === $request->user()?->id, 404);

        $transaction->delete();

        return response()->json(status: 204);
    }
}
