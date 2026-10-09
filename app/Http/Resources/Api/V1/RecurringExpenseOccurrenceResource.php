<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringExpenseOccurrenceResource extends JsonResource
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
            'recurring_expense_id' => $this->recurring_expense_id,
            'period' => $this->period?->toDateString(),
            'status' => $this->status,
            'transaction_id' => $this->transaction_id,
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
        ];
    }
}
