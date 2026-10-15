<?php

namespace App\Http\Controllers;

use App\Services\ReceiptImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GmailForwardingVerificationController extends Controller
{
    public function index(Request $request, ReceiptImportService $service): JsonResponse
    {
        $address = $service->currentAddressFor($request->user());
        $verifications = $address === null
            ? []
            : DB::table('inbound_receipts')
                ->where('user_id', $request->user()->id)
                ->where('recipient', $address)
                ->whereNotNull('gmail_verification_url')
                ->whereNull('gmail_verification_dismissed_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get([
                    'id',
                    'gmail_forwarding_source as source',
                    'gmail_verification_url as url',
                    'created_at',
                ]);

        return response()->json(['verifications' => $verifications])
            ->header('Cache-Control', 'private, no-store');
    }

    public function dismiss(Request $request, ReceiptImportService $service, int $verification): JsonResponse
    {
        $address = $service->currentAddressFor($request->user());

        abort_if($address === null, 404);

        $updated = DB::table('inbound_receipts')
            ->where('id', $verification)
            ->where('user_id', $request->user()->id)
            ->where('recipient', $address)
            ->whereNotNull('gmail_verification_url')
            ->whereNull('gmail_verification_dismissed_at')
            ->update([
                'gmail_verification_dismissed_at' => now(),
                'updated_at' => now(),
            ]);

        abort_if($updated === 0, 404);

        return response()->json(status: 204)
            ->header('Cache-Control', 'private, no-store');
    }
}
