<?php

namespace App\Services;

use App\Enums\BankLineStatus;
use App\Enums\PaymentDirection;
use App\Exceptions\BusinessException;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Reconciles bank statement lines with the payments recorded in the ERP.
 */
class BankReconciliationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): BankStatementLine
    {
        return BankStatementLine::create([
            'reference' => $data['reference'] ?? null,
            'label' => $data['label'],
            'amount' => round((float) $data['amount'], 2),
            'direction' => PaymentDirection::from($data['direction'] ?? PaymentDirection::Incoming->value),
            'value_date' => $data['value_date'] ?? today(),
            'status' => BankLineStatus::Unmatched,
            'created_by' => $user?->id ?? Auth::id(),
        ]);
    }

    public function match(BankStatementLine $line, Payment $payment): BankStatementLine
    {
        if ($line->isMatched()) {
            throw new BusinessException('Cette ligne de relevé est déjà rapprochée.');
        }

        if ($this->isPaymentAlreadyMatched($payment)) {
            throw new BusinessException('Ce paiement est déjà rapproché avec une autre ligne.');
        }

        if ($line->direction !== $payment->direction) {
            throw new BusinessException('Le sens de l\'opération ne correspond pas à celui du paiement.');
        }

        if (abs((float) $line->amount - (float) $payment->amount) > 0.01) {
            throw new BusinessException(sprintf(
                'Le montant de la ligne (%s) diffère de celui du paiement (%s).',
                number_format((float) $line->amount, 2, ',', ' '),
                number_format((float) $payment->amount, 2, ',', ' '),
            ));
        }

        $line->payment_id = $payment->id;
        $line->status = BankLineStatus::Matched;
        $line->matched_at = now();
        $line->save();

        return $line->load('payment');
    }

    public function unmatch(BankStatementLine $line): BankStatementLine
    {
        if (! $line->isMatched()) {
            throw new BusinessException('Cette ligne n\'est pas rapprochée.');
        }

        $line->payment_id = null;
        $line->status = BankLineStatus::Unmatched;
        $line->matched_at = null;
        $line->save();

        return $line;
    }

    /**
     * Unmatched payments that could settle this line (same direction and amount).
     *
     * @return Collection<int, Payment>
     */
    public function suggestions(BankStatementLine $line): Collection
    {
        $matchedPaymentIds = BankStatementLine::query()
            ->whereNotNull('payment_id')
            ->select('payment_id');

        return Payment::query()
            ->where('direction', $line->direction)
            ->where('amount', $line->amount)
            ->whereNotIn('id', $matchedPaymentIds)
            ->orderByDesc('payment_date')
            ->limit(5)
            ->get();
    }

    private function isPaymentAlreadyMatched(Payment $payment): bool
    {
        return BankStatementLine::query()->where('payment_id', $payment->id)->exists();
    }
}
