<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
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
            'name' => $this->name,
            'last_four_digits' => $this->last_four_digits,
            'closing_day' => $this->closing_day,
            'due_day' => $this->due_day,
            'is_active' => $this->is_active,
            'billing_cycles' => CardBillingCycleResource::collection($this->whenLoaded('billingCycles')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
