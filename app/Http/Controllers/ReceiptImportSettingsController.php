<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReceiptImportSettingsController extends Controller
{
    private const PROVIDERS = [
        'uber' => 'Uber',
        'didi' => 'DiDi',
    ];

    public function index(Request $request): JsonResponse
    {
        $settings = DB::table('receipt_import_settings')
            ->where('user_id', $request->user()->id)
            ->get(['provider', 'category_id', 'place'])
            ->keyBy('provider');

        $providers = [];

        foreach (self::PROVIDERS as $key => $name) {
            $providers[] = [
                'key' => $key,
                'name' => $name,
                'category_id' => $settings->get($key)?->category_id,
                'place' => $settings->get($key)?->place ?? $name,
            ];
        }

        return response()->json(['providers' => $providers])
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $provider): JsonResponse
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);

        $validated = $request->validate([
            'category_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $request->user()->id)
                    ->where('is_active', true)
                    ->whereIn('scope', ['expense', 'both']),
            ],
            'place' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $savedPlace = DB::table('receipt_import_settings')
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->value('place');
        $place = $savedPlace;

        if (array_key_exists('place', $validated)) {
            $place = trim($validated['place'] ?? '');
            $place = $place === '' ? null : $place;
        }

        DB::table('receipt_import_settings')->upsert(
            [[
                'user_id' => $request->user()->id,
                'provider' => $provider,
                'category_id' => $validated['category_id'],
                'place' => $place,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id', 'provider'],
            ['category_id', 'place', 'updated_at'],
        );

        return response()->json([
            'key' => $provider,
            'name' => self::PROVIDERS[$provider],
            'category_id' => $validated['category_id'],
            'place' => $place ?? self::PROVIDERS[$provider],
        ])->header('Cache-Control', 'private, no-store');
    }
}
