<?php

namespace App\Services;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Creates payment rows for any payable document (invoices, purchase orders).
 *
 * The calling service owns the business guards and the balance recalculation,
 * this class only centralises the reference and the common columns.
 */
class PaymentService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Model $payable, PaymentDirection $direction, array $data, ?User $user = null): Payment
    {
        return Payment::create([
            'reference' => $this->references->next('payment', 'PAY', today()),
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'direction' => $direction,
            'method' => PaymentMethod::from($data['method'] ?? PaymentMethod::Cash->value),
            'amount' => round((float) $data['amount'], 2),
            'payment_date' => $data['payment_date'] ?? today(),
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user?->id ?? Auth::id(),
        ]);
    }
}
