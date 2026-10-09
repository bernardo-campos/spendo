<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecurringExpenseRequest;
use App\Http\Requests\UpdateRecurringExpenseRequest;
use App\Http\Resources\Api\V1\RecurringExpenseOccurrenceResource;
use App\Http\Resources\Api\V1\RecurringExpenseResource;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use App\Models\Transaction;
use App\Services\CardPaymentDateService;
use App\Services\RecurringExpenseNoteService;
use App\Services\RecurringExpensePreviewService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecurringExpenseController extends Controller
{
    public function __construct(
        private RecurringExpensePreviewService $previewService,
        private CardPaymentDateService $cardPaymentDateService,
        private RecurringExpenseNoteService $noteService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $expenses = RecurringExpense::query()
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', now()->startOfMonth()->toDateString()))
            ->with(['card', 'category'])
            ->orderBy('description')
            ->get();

        if ($request->routeIs('api.v1.*')) {
            return RecurringExpenseResource::collection($expenses)->response();
        }

        return response()->json($expenses);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $validated['period']);

        $items = $this->previewService->forPeriod($request->user()->id, $month);

        return response()->json($request->routeIs('api.v1.*') ? ['data' => $items] : $items);
    }

    public function store(StoreRecurringExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;
        $validated['amount'] = $validated['amount_type'] === 'fixed' ? $validated['amount'] : null;
        $validated['card_id'] = $validated['payment_method'] === 'credit' ? $validated['card_id'] : null;

        $expense = RecurringExpense::query()->create($validated);

        $expense->load(['card', 'category']);

        if ($request->routeIs('api.v1.*')) {
            return (new RecurringExpenseResource($expense))->response()->setStatusCode(201);
        }

        return response()->json($expense, 201);
    }

    public function update(UpdateRecurringExpenseRequest $request, RecurringExpense $recurringExpense): JsonResponse
    {
        abort_unless($recurringExpense->user_id === $request->user()->id, 404);

        $validated = $request->validated();
        $effectiveMonth = CarbonImmutable::createFromFormat('!Y-m', $validated['effective_period'])->startOfMonth();
        unset($validated['effective_period']);
        $validated['amount'] = $validated['amount_type'] === 'fixed' ? $validated['amount'] : null;
        $validated['card_id'] = $validated['payment_method'] === 'credit' ? $validated['card_id'] : null;

        abort_if($recurringExpense->ends_on !== null && $effectiveMonth->gt($recurringExpense->ends_on), 422, 'El cambio debe empezar durante la vigencia de la recurrencia.');
        abort_if(
            ($validated['number_occurrences_in_notes'] ?? $recurringExpense->number_occurrences_in_notes)
                && array_key_exists('ends_on', $validated) && $validated['ends_on'] === null,
            422,
            'Indique una fecha de fin para numerar las repeticiones.'
        );
        abort_if(isset($validated['ends_on']) && $validated['ends_on'] < $effectiveMonth->toDateString(), 422, 'La fecha de fin debe ser posterior al inicio del cambio.');
        abort_if(
            $recurringExpense->occurrences()->where('period', '>=', $effectiveMonth->toDateString())->exists(),
            422,
            'Ya hay meses confirmados u omitidos desde el período elegido.'
        );

        $expense = DB::transaction(function () use ($recurringExpense, $validated, $effectiveMonth): RecurringExpense {
            if ($effectiveMonth->lte($recurringExpense->starts_on->startOfMonth())) {
                $recurringExpense->update($validated);

                return $recurringExpense;
            }

            $oldEnd = $recurringExpense->ends_on?->toDateString();
            $recurringExpense->update(['ends_on' => $effectiveMonth->subDay()->toDateString()]);

            return RecurringExpense::query()->create([
                ...$recurringExpense->only([
                    'user_id', 'category_id', 'card_id', 'description', 'place', 'payment_method',
                    'currency', 'amount_type', 'amount', 'day_of_month', 'is_active', 'series_key',
                    'notes', 'number_occurrences_in_notes',
                ]),
                ...$validated,
                'starts_on' => $effectiveMonth->toDateString(),
                'ends_on' => array_key_exists('ends_on', $validated) ? $validated['ends_on'] : $oldEnd,
            ]);
        });

        $expense->load(['card', 'category']);

        if ($request->routeIs('api.v1.*')) {
            return (new RecurringExpenseResource($expense))->response();
        }

        return response()->json($expense);
    }

    public function decide(Request $request, RecurringExpense $recurringExpense): JsonResponse
    {
        abort_unless($recurringExpense->user_id === $request->user()->id, 404);
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'status' => ['required', Rule::in(['confirmed', 'skipped'])],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $validated['period'])->startOfMonth();
        abort_unless($this->previewService->appliesTo($recurringExpense, $month), 422, 'La recurrencia no corresponde a ese mes.');
        $chargeDate = $this->previewService->chargeDate($recurringExpense, $month);
        abort_if($chargeDate->isFuture(), 422, 'Todavía no llegó la fecha del cargo.');
        $amount = $validated['amount'] ?? $recurringExpense->amount;
        abort_if($validated['status'] === 'confirmed' && $amount === null, 422, 'Debe confirmar el importe de este mes.');
        abort_if($validated['status'] === 'confirmed' && $recurringExpense->payment_method === 'credit' && $recurringExpense->card === null, 422, 'La tarjeta de esta recurrencia ya no existe. Edite la regla antes de confirmar.');

        $occurrence = DB::transaction(function () use ($request, $recurringExpense, $month, $chargeDate, $validated, $amount): RecurringExpenseOccurrence {
            $recurringExpense->newQuery()->whereKey($recurringExpense->id)->lockForUpdate()->firstOrFail();
            abort_if(
                $recurringExpense->occurrences()->whereDate('period', $month->toDateString())->exists(),
                409,
                'Este mes ya fue confirmado u omitido.'
            );
            $inserted = DB::table((new RecurringExpenseOccurrence)->getTable())->insertOrIgnore([
                'recurring_expense_id' => $recurringExpense->id,
                'period' => $month->toDateString(),
                'status' => $validated['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            abort_if($inserted === 0, 409, 'Este mes ya fue confirmado u omitido.');

            $occurrence = $recurringExpense->occurrences()->where('period', $month->toDateString())->firstOrFail();

            if ($validated['status'] === 'confirmed') {
                $paymentDate = $this->cardPaymentDateService->resolve(
                    $chargeDate->toDateString(),
                    $recurringExpense->payment_method,
                    $recurringExpense->card,
                )['date'];
                $transaction = Transaction::query()->create([
                    'user_id' => $request->user()->id,
                    'category_id' => $recurringExpense->category_id,
                    'payment_method' => $recurringExpense->payment_method,
                    'card_id' => $recurringExpense->card_id,
                    'type' => 'expense',
                    'description' => $recurringExpense->description,
                    'place' => $recurringExpense->place,
                    'amount' => $amount,
                    'currency' => $recurringExpense->currency,
                    'purchase_date' => $chargeDate->toDateString(),
                    'payment_date' => $paymentDate,
                    'notes' => $this->noteService->forMonth($recurringExpense, $month),
                ]);
                $occurrence->update(['transaction_id' => $transaction->id]);
            }

            return $occurrence;
        });

        $occurrence->load('transaction');

        if ($request->routeIs('api.v1.*')) {
            return (new RecurringExpenseOccurrenceResource($occurrence))->response()->setStatusCode(201);
        }

        return response()->json($occurrence, 201);
    }

    public function saveOccurrence(Request $request, RecurringExpense $recurringExpense): JsonResponse
    {
        abort_unless($recurringExpense->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'status' => ['required', Rule::in(['confirmed', 'skipped'])],
            'amount' => ['required_if:status,confirmed', 'nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $month = CarbonImmutable::createFromFormat('!Y-m', $validated['period'])->startOfMonth();
        abort_unless($this->previewService->appliesTo($recurringExpense, $month), 422, 'La recurrencia no corresponde a ese mes.');

        $chargeDate = $this->previewService->chargeDate($recurringExpense, $month);
        abort_if($validated['status'] === 'confirmed' && $recurringExpense->amount_type === 'variable' && $chargeDate->isFuture(), 422, 'Todavía no llegó la fecha del cargo.');
        abort_if($validated['status'] === 'confirmed' && $recurringExpense->payment_method === 'credit' && $recurringExpense->card === null, 422, 'La tarjeta de esta recurrencia ya no existe.');

        $occurrence = DB::transaction(function () use ($request, $recurringExpense, $month, $chargeDate, $validated): RecurringExpenseOccurrence {
            $recurringExpense->newQuery()->whereKey($recurringExpense->id)->lockForUpdate()->firstOrFail();

            $occurrence = $recurringExpense->occurrences()
                ->whereDate('period', $month->toDateString())
                ->first();

            if ($occurrence === null) {
                $occurrence = $recurringExpense->occurrences()->create([
                    'period' => $month->toDateString(),
                    'status' => 'skipped',
                ]);
            }
            $transaction = $occurrence->transaction;

            if ($validated['status'] === 'skipped') {
                $occurrence->update(['status' => 'skipped', 'transaction_id' => null]);
                $transaction?->delete();

                return $occurrence;
            }

            $paymentDate = $this->cardPaymentDateService->resolve(
                $chargeDate->toDateString(),
                $recurringExpense->payment_method,
                $recurringExpense->card,
            )['date'];

            $attributes = [
                'user_id' => $request->user()->id,
                'category_id' => $recurringExpense->category_id,
                'payment_method' => $recurringExpense->payment_method,
                'card_id' => $recurringExpense->card_id,
                'type' => 'expense',
                'description' => $validated['description'] ?? $recurringExpense->description,
                'place' => $recurringExpense->place,
                'amount' => $validated['amount'],
                'currency' => $recurringExpense->currency,
                'purchase_date' => $chargeDate->toDateString(),
                'payment_date' => $paymentDate,
                'notes' => $this->noteService->forMonth($recurringExpense, $month),
            ];

            if ($transaction) {
                $transaction->update($attributes);
            } else {
                $transaction = Transaction::query()->create($attributes);
            }

            $occurrence->update(['status' => 'confirmed', 'transaction_id' => $transaction->id]);

            return $occurrence;
        });

        $occurrence->load('transaction');

        if ($request->routeIs('api.v1.*')) {
            return (new RecurringExpenseOccurrenceResource($occurrence))->response();
        }

        return response()->json($occurrence);
    }
}
