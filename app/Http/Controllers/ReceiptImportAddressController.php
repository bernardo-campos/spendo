<?php

namespace App\Http\Controllers;

use App\Services\ReceiptImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptImportAddressController extends Controller
{
    public function show(Request $request, ReceiptImportService $service): JsonResponse
    {
        return $this->addressResponse($service->currentAddressFor($request->user()));
    }

    public function store(Request $request, ReceiptImportService $service): JsonResponse
    {
        return $this->addressResponse($service->addressFor($request->user()));
    }

    public function rotate(Request $request, ReceiptImportService $service): JsonResponse
    {
        return $this->addressResponse($service->addressFor($request->user(), rotate: true));
    }

    private function addressResponse(?string $address): JsonResponse
    {
        return response()->json(['address' => $address])
            ->header('Cache-Control', 'private, no-store');
    }
}
