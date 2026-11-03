<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BankStatementLine */
class BankLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'label' => $this->label,
            'amount' => (float) $this->amount,
            'direction' => $this->direction->value,
            'direction_label' => $this->direction->label(),
            'value_date' => $this->value_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_id' => $this->payment_id,
            'payment' => PaymentResource::make($this->whenLoaded('payment')),
            'matched_at' => $this->matched_at?->toIso8601String(),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
