<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'category_id' => $this->category_id,
            'payment_method' => $this->payment_method,
            'card_id' => $this->card_id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => $this->amount,
            'purchase_date' => $this->purchase_date,
            'payment_date' => $this->payment_date,
            'notes' => $this->notes,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'card' => CardResource::make($this->whenLoaded('card')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'installment_plan' => InstallmentPlanResource::make($this->whenLoaded('installmentPlan')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
