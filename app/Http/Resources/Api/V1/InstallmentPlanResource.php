<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'card_id' => $this->card_id,
            'installments_count' => $this->installments_count,
            'total_amount' => $this->total_amount,
            'first_due_date' => $this->first_due_date,
            'status' => $this->status,
            'transaction' => TransactionResource::make($this->whenLoaded('transaction')),
            'card' => CardResource::make($this->whenLoaded('card')),
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
