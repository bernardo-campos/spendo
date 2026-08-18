<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
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
            'installment_plan_id' => $this->installment_plan_id,
            'installment_number' => $this->installment_number,
            'amount' => $this->amount,
            'due_date' => $this->due_date,
            'due_date_is_estimated' => $this->due_date_is_estimated,
            'paid_at' => $this->paid_at,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
