<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionCurrency;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCurrencyExchangeRequest;
use App\Models\CurrencyExchange;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CurrencyExchangeController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_currency' => ['required', Rule::enum(TransactionCurrency::class)],
            'target_currency' => ['required', Rule::enum(TransactionCurrency::class), 'different:source_currency'],
        ]);

        $exchange = CurrencyExchange::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($query) use ($validated): void {
                $query->where(function ($query) use ($validated): void {
                    $query->whereHas('expense', fn ($leg) => $leg->where('currency', $validated['source_currency']))
                        ->whereHas('income', fn ($leg) => $leg->where('currency', $validated['target_currency']));
                })->orWhere(function ($query) use ($validated): void {
                    $query->whereHas('expense', fn ($leg) => $leg->where('currency', $validated['target_currency']))
                        ->whereHas('income', fn ($leg) => $leg->where('currency', $validated['source_currency']));
                });
            })
            ->with(['expense', 'income'])
            ->latest('id')
            ->first();

        $payload = $exchange ? $this->payload($exchange) : null;

        return response()->json($request->routeIs('api.v1.*') ? ['data' => $payload] : $payload);
    }

    public function store(StoreCurrencyExchangeRequest $request): JsonResponse
    {
        $exchange = DB::transaction(function () use ($request): CurrencyExchange {
            $exchange = CurrencyExchange::query()->create(['user_id' => $request->user()->id]);
            $this->saveLegs($exchange, $request->validated());

            return $exchange;
        });

        return $this->respond($request, $exchange, 201);
    }

    public function show(Request $request, CurrencyExchange $currencyExchange): JsonResponse
    {
        $this->authorizeOwner($request, $currencyExchange);

        return $this->respond($request, $currencyExchange);
    }

    public function update(StoreCurrencyExchangeRequest $request, CurrencyExchange $currencyExchange): JsonResponse
    {
        $this->authorizeOwner($request, $currencyExchange);
        DB::transaction(fn () => $this->saveLegs($currencyExchange, $request->validated()));

        return $this->respond($request, $currencyExchange);
    }

    public function destroy(Request $request, CurrencyExchange $currencyExchange): JsonResponse
    {
        $this->authorizeOwner($request, $currencyExchange);

        DB::transaction(function () use ($currencyExchange): void {
            $currencyExchange->expense()->delete();
            $currencyExchange->income()->delete();
            $currencyExchange->delete();
        });

        return response()->json(status: 204);
    }

    /** @param array<string, mixed> $data */
    private function saveLegs(CurrencyExchange $exchange, array $data): void
    {
        $shared = [
            'user_id' => $exchange->user_id,
            'exchange_id' => $exchange->id,
            'description' => $data['description'],
            'purchase_date' => $data['purchase_date'],
            'payment_date' => $data['purchase_date'],
            'notes' => $data['notes'] ?? null,
            'category_id' => null,
            'card_id' => null,
        ];

        Transaction::query()->updateOrCreate(
            ['exchange_id' => $exchange->id, 'type' => 'expense'],
            [...$shared, 'type' => 'expense', 'amount' => $data['source_amount'], 'currency' => $data['source_currency'], 'place' => $data['place'] ?? null, 'payment_method' => null],
        );
        Transaction::query()->updateOrCreate(
            ['exchange_id' => $exchange->id, 'type' => 'income'],
            [...$shared, 'type' => 'income', 'amount' => $data['target_amount'], 'currency' => $data['target_currency'], 'place' => null, 'payment_method' => null],
        );
    }

    private function authorizeOwner(Request $request, CurrencyExchange $exchange): void
    {
        abort_unless($exchange->user_id === $request->user()?->id, 404);
    }

    private function respond(Request $request, CurrencyExchange $exchange, int $status = 200): JsonResponse
    {
        $payload = $this->payload($exchange->fresh(['expense', 'income']));

        return response()->json($request->routeIs('api.v1.*') ? ['data' => $payload] : $payload, $status);
    }

    /** @return array<string, mixed> */
    private function payload(CurrencyExchange $exchange): array
    {
        return [
            'id' => $exchange->id,
            'created_at' => $exchange->created_at,
            'expense' => $exchange->expense,
            'income' => $exchange->income,
        ];
    }
}
