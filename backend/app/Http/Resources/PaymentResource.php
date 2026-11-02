<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'direction' => $this->direction->value,
            'direction_label' => $this->direction->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount' => (float) $this->amount,
            'payment_date' => $this->payment_date?->toDateString(),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
