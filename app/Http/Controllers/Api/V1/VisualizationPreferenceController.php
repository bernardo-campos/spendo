<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVisualizationPreferenceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisualizationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        return response()->json([
            'expense_list' => $user->expenseListDisplayPreferences(),
        ]);
    }

    public function update(UpdateVisualizationPreferenceRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $user->update([
            'expense_list_display_preferences' => $request->validated('expense_list'),
        ]);

        return response()->json([
            'expense_list' => $user->expenseListDisplayPreferences(),
        ]);
    }
}
