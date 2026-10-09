<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringExpenseResource extends JsonResource
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
            'description' => $this->description,
            'place' => $this->place,
            'category_id' => $this->category_id,
            'card_id' => $this->card_id,
            'payment_method' => $this->payment_method,
            'currency' => $this->currency,
            'amount_type' => $this->amount_type,
            'amount' => $this->amount,
            'day_of_month' => $this->day_of_month,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'is_active' => $this->is_active,
            'card' => new CardResource($this->whenLoaded('card')),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
