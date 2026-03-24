<?php

namespace App\Http\Controllers;

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

        $income = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'income')
            ->whereBetween('purchase_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $expense = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereBetween('purchase_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $upcomingInstallments = Installment::query()
            ->whereHas('installmentPlan', fn ($query) => $query->where('user_id', $user->id))
            ->where('status', 'pending')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'income_month' => number_format((float) $income, 2, '.', ''),
            'expense_month' => number_format((float) $expense, 2, '.', ''),
            'net_month' => number_format((float) $income - (float) $expense, 2, '.', ''),
            'upcoming_installments' => $upcomingInstallments,
        ]);
    }
}
