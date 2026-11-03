<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentDirection;
use App\Enums\SalesOrderStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\LineTotals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly PaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            $lines = $this->prepareLines($data['lines'] ?? []);
            $totals = LineTotals::summarize($lines);

            if ($lines === []) {
                throw new BusinessException('Une facture doit contenir au moins une ligne.');
            }

            $type = InvoiceType::from($data['type'] ?? InvoiceType::Invoice->value);
            $issueDate = CarbonImmutable::parse($data['issue_date'] ?? today());
            $dueDate = isset($data['due_date'])
                ? CarbonImmutable::parse($data['due_date'])
                : $issueDate->addDays(30);

            $invoice = Invoice::create([
                'reference' => $this->references->next('invoice', $type->referencePrefix(), $issueDate),
                'client_id' => $data['client_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'type' => $type,
                'status' => InvoiceStatus::Draft,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id ?? Auth::id(),
                'amount_paid' => 0,
                'balance_due' => $totals['total'],
            ] + $totals);

            foreach ($lines as $line) {
                $invoice->lines()->create($line);
            }

            return $invoice->load(['client', 'lines.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw InvalidStatusTransitionException::from('facture', $invoice->status->label(), 'modifier');
        }

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->fill(array_intersect_key($data, array_flip(['client_id', 'due_date', 'notes'])));

            if (isset($data['lines'])) {
                $lines = $this->prepareLines($data['lines']);
                $totals = LineTotals::summarize($lines);

                $invoice->lines()->delete();
                foreach ($lines as $line) {
                    $invoice->lines()->create($line);
                }

                $invoice->fill($totals);
                $invoice->balance_due = $totals['total'];
                $invoice->unsetRelation('lines');
            }

            $invoice->save();

            return $invoice->load(['client', 'lines.product']);
        });
    }

    public function send(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw InvalidStatusTransitionException::from('facture', $invoice->status->label(), 'envoyer');
        }

        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->status = InvoiceStatus::Sent;
            $invoice->save();
        }

        $this->refreshStatus($invoice);

        return $invoice->fresh(['client', 'lines.product', 'payments']);
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->payments()->exists()) {
            throw new BusinessException('Impossible d\'annuler une facture qui a déjà reçu des encaissements.');
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            return $invoice;
        }

        $invoice->status = InvoiceStatus::Cancelled;
        $invoice->save();

        return $invoice->fresh(['client', 'lines.product']);
    }

    /**
     * Builds an invoice from a fully delivered sales order.
     */
    public function fromSalesOrder(SalesOrder $order, ?User $user = null): Invoice
    {
        $order->loadMissing('lines.product');

        if (! in_array($order->status, [SalesOrderStatus::Delivered, SalesOrderStatus::PartiallyDelivered], true)) {
            throw new BusinessException('Seule une commande livrée (même partiellement) peut être facturée.');
        }

        if ($order->invoices()->exists()) {
            throw new BusinessException('Cette commande a déjà été facturée.');
        }

        $lines = $order->lines
            ->filter(fn ($line) => (float) $line->delivered_quantity > 0)
            ->map(fn ($line) => [
                'product_id' => $line->product_id,
                'description' => $line->description ?: $line->product?->name,
                'quantity' => (float) $line->delivered_quantity,
                'unit_price' => (float) $line->unit_price,
                'discount' => (float) $line->discount,
                'tax_rate' => (float) $line->tax_rate,
            ])
            ->values()
            ->all();

        if ($lines === []) {
            throw new BusinessException('Aucune quantité livrée à facturer sur cette commande.');
        }

        return DB::transaction(function () use ($order, $lines, $user) {
            $invoice = $this->create([
                'client_id' => $order->client_id,
                'sales_order_id' => $order->id,
                'issue_date' => today()->toDateString(),
                'lines' => $lines,
            ], $user);

            $order->status = SalesOrderStatus::Invoiced;
            $order->save();

            return $invoice;
        });
    }

    /**
     * @param  array{amount: float|int|string, method?: string, payment_date?: string, reference_number?: string|null, notes?: string|null}  $data
     */
    public function recordPayment(Invoice $invoice, array $data, ?User $user = null): Payment
    {
        if (! $invoice->status->isPayable()) {
            throw new BusinessException('Cette facture ne peut pas recevoir de paiement dans son statut actuel.');
        }

        $amount = round((float) $data['amount'], 2);
        $balance = (float) $invoice->balance_due;

        if ($amount <= 0) {
            throw new BusinessException('Le montant du paiement doit être supérieur à zéro.');
        }

        if ($amount - $balance > 0.001) {
            throw new BusinessException(sprintf(
                'Le montant (%s MAD) dépasse le solde restant dû (%s MAD).',
                number_format($amount, 2, ',', ' '),
                number_format($balance, 2, ',', ' '),
            ));
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $user) {
            $payment = $this->payments->create(
                $invoice,
                PaymentDirection::Incoming,
                ['amount' => $amount] + $data,
                $user,
            );

            $this->refreshStatus($invoice);

            return $payment->load('creator');
        });
    }

    /**
     * Recomputes the paid amount, the outstanding balance and the status.
     */
    public function refreshStatus(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
            return $invoice;
        }

        $paid = round((float) $invoice->payments()->sum('amount'), 2);
        $balance = round((float) $invoice->total - $paid, 2);

        $invoice->amount_paid = $paid;
        $invoice->balance_due = max(0, $balance);

        $invoice->status = match (true) {
            $balance <= 0 => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::PartiallyPaid,
            $invoice->due_date->isBefore(today()) => InvoiceStatus::Overdue,
            default => InvoiceStatus::Sent,
        };

        $invoice->save();

        return $invoice;
    }

    /**
     * Flags every invoice past its due date. Returns the number of updated rows.
     */
    public function markOverdue(): int
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '<', today())
            ->update(['status' => InvoiceStatus::Overdue->value]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function prepareLines(array $lines): array
    {
        $prepared = [];

        foreach ($lines as $line) {
            $product = isset($line['product_id']) && $line['product_id'] !== null
                ? Product::query()->find($line['product_id'])
                : null;

            $quantity = (float) $line['quantity'];
            $unitPrice = (float) ($line['unit_price'] ?? $product?->sale_price ?? 0);
            $discount = (float) ($line['discount'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? $product?->tax_rate ?? 0);
            $description = $line['description'] ?? $product?->name;

            if (blank($description)) {
                throw new BusinessException('Chaque ligne de facture doit avoir une description.');
            }

            $prepared[] = [
                'product_id' => $product?->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'line_total' => LineTotals::lineTotal($quantity, $unitPrice, $discount),
            ];
        }

        return $prepared;
    }
}
