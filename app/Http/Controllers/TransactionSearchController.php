<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchTransactionRequest;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class TransactionSearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SearchTransactionRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $filters['type'])
            ->with(['category', 'card', 'tags', 'installmentPlan.installments']);

        if ($filters['date_scope'] === 'period') {
            $start = Carbon::createFromFormat('!Y-m', $filters['period']);
            $query->whereBetween('purchase_date', [$start->toDateString(), $start->copy()->endOfMonth()]);
        } elseif ($filters['date_scope'] === 'range') {
            $query->whereBetween('purchase_date', [$filters['date_from'], Carbon::parse($filters['date_to'])->endOfDay()]);
        }

        foreach (['description', 'place', 'notes'] as $field) {
            if (isset($filters[$field]) && trim($filters[$field]) !== '') {
                $query->whereLike($field, '%'.trim($filters[$field]).'%');
            }
        }

        foreach (['category_id', 'card_id', 'currency', 'payment_method'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }
        if (isset($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }
        if (! empty($filters['tag_ids'])) {
            $query->whereHas('tags', function (Builder $query) use ($filters): void {
                $query->whereIn('tags.id', $filters['tag_ids']);
            });
        }

        $totals = (clone $query)->toBase()
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        return TransactionResource::collection($query
            ->latest('purchase_date')->latest('id')->paginate(25))
            ->additional(['totals' => [
                'ARS' => (float) ($totals['ARS'] ?? 0),
                'USD' => (float) ($totals['USD'] ?? 0),
            ]])->response();
    }
}
