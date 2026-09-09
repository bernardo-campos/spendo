<?php

namespace App\Http\Controllers;

use App\Enums\TransactionCurrency;
use App\Models\Installment;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $totals = Transaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('purchase_date', [$startOfMonth, $endOfMonth])
            ->select('currency')
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income")
            ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense")
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $upcomingInstallments = Installment::query()
            ->whereHas('installmentPlan', fn ($query) => $query->where('user_id', $user->id))
            ->where('status', 'pending')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'totals' => collect(TransactionCurrency::cases())
                ->mapWithKeys(function (TransactionCurrency $currency) use ($totals): array {
                    $currencyTotals = $totals->get($currency->value);
                    $income = (float) ($currencyTotals?->income ?? 0);
                    $expense = (float) ($currencyTotals?->expense ?? 0);

                    return [$currency->value => [
                        'income' => number_format($income, 2, '.', ''),
                        'expense' => number_format($expense, 2, '.', ''),
                        'net' => number_format($income - $expense, 2, '.', ''),
                    ]];
                }),
            'upcoming_installments' => $upcomingInstallments,
        ]);
    }
}
