<?php

namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\LineTotals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly StockService $stock,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $lines = $this->prepareLines($data['lines']);
            $totals = LineTotals::summarize($lines);

            $order = SalesOrder::create([
                'reference' => $this->references->next('sales_order', 'SO'),
                'client_id' => $data['client_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => SalesOrderStatus::Draft,
                'order_date' => $data['order_date'] ?? today(),
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id ?? Auth::id(),
            ] + $totals);

            $this->replaceLines($order, $lines);

            return $order->load(['client', 'warehouse', 'lines.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SalesOrder $order, array $data): SalesOrder
    {
        if (! $order->status->canBeEdited()) {
            throw InvalidStatusTransitionException::from('commande client', $order->status->label(), 'modifier');
        }

        return DB::transaction(function () use ($order, $data) {
            $lines = $this->prepareLines($data['lines'] ?? $order->lines->map(fn ($line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount' => $line->discount,
                'tax_rate' => $line->tax_rate,
            ])->all());

            $totals = LineTotals::summarize($lines);

            $order->fill(array_intersect_key($data, array_flip([
                'client_id', 'warehouse_id', 'order_date', 'delivery_date', 'notes',
            ])));

            if (isset($data['lines'])) {
                $this->replaceLines($order, $lines);
            }

            $order->fill($totals)->save();

            return $order->load(['client', 'warehouse', 'lines.product']);
        });
    }

    public function confirm(SalesOrder $order): SalesOrder
    {
        $order->loadMissing('lines.product', 'warehouse');

        if ($order->status !== SalesOrderStatus::Draft) {
            throw InvalidStatusTransitionException::from('commande client', $order->status->label(), 'confirmer');
        }

        if ($order->lines->isEmpty()) {
            throw new BusinessException('Une commande sans ligne ne peut pas être confirmée.');
        }

        DB::transaction(function () use ($order) {
            foreach ($order->lines as $line) {
                $this->stock->reserve($line->product, $order->warehouse, (float) $line->quantity);
            }

            $order->status = SalesOrderStatus::Confirmed;
            $order->save();
        });

        return $order->fresh(['client', 'warehouse', 'lines.product']);
    }

    /**
     * Ships the given quantities (defaults to everything still pending).
     *
     * @param  array<int, float|int|string>  $quantities  sales_order_line_id => quantity
     */
    public function deliver(SalesOrder $order, array $quantities = []): SalesOrder
    {
        $order->loadMissing('lines.product', 'warehouse');

        if (! in_array($order->status, [SalesOrderStatus::Confirmed, SalesOrderStatus::PartiallyDelivered], true)) {
            throw InvalidStatusTransitionException::from('commande client', $order->status->label(), 'livrer');
        }

        $shipped = 0.0;

        DB::transaction(function () use ($order, $quantities, &$shipped) {
            foreach ($order->lines as $line) {
                $remaining = round((float) $line->quantity - (float) $line->delivered_quantity, 3);
                $requested = array_key_exists($line->id, $quantities)
                    ? (float) $quantities[$line->id]
                    : $remaining;

                $quantity = min($requested, $remaining);

                if ($quantity <= 0) {
                    continue;
                }

                $this->stock->decrease($line->product, $order->warehouse, $quantity, [
                    'reference' => $order,
                    'consume_reservation' => true,
                    'reason' => 'Livraison '.$order->reference,
                ]);

                $line->delivered_quantity = round((float) $line->delivered_quantity + $quantity, 3);
                $line->save();

                $shipped += $quantity;
            }

            if ($shipped <= 0) {
                throw new BusinessException('Aucune quantité à livrer sur cette commande.');
            }

            $order->refresh()->load('lines');

            if ($order->isFullyDelivered()) {
                $order->status = SalesOrderStatus::Delivered;
                $order->delivered_at = now();
                $order->delivery_date ??= today();
            } else {
                $order->status = SalesOrderStatus::PartiallyDelivered;
            }

            $order->save();
        });

        return $order->fresh(['client', 'warehouse', 'lines.product']);
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        $order->loadMissing('lines.product', 'warehouse');

        if (! $order->status->isCancellable()) {
            throw InvalidStatusTransitionException::from('commande client', $order->status->label(), 'annuler');
        }

        DB::transaction(function () use ($order) {
            if ($order->status === SalesOrderStatus::Confirmed) {
                foreach ($order->lines as $line) {
                    $pending = round((float) $line->quantity - (float) $line->delivered_quantity, 3);

                    if ($pending > 0) {
                        $this->stock->release($line->product, $order->warehouse, $pending);
                    }
                }
            }

            $order->status = SalesOrderStatus::Cancelled;
            $order->save();
        });

        return $order->fresh(['client', 'warehouse', 'lines.product']);
    }

    public function invoice(SalesOrder $order, ?User $user = null): Invoice
    {
        $invoice = $this->invoices->fromSalesOrder($order, $user);

        return $invoice;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function prepareLines(array $lines): array
    {
        return array_values(array_map(function (array $line) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($line['product_id']);

            $quantity = (float) $line['quantity'];
            $unitPrice = (float) ($line['unit_price'] ?? $product->sale_price);
            $discount = (float) ($line['discount'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? $product->tax_rate);

            return [
                'product_id' => $product->id,
                'description' => $line['description'] ?? $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'line_total' => LineTotals::lineTotal($quantity, $unitPrice, $discount),
            ];
        }, $lines));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function replaceLines(SalesOrder $order, array $lines): void
    {
        $order->lines()->delete();

        foreach ($lines as $line) {
            $order->lines()->create($line);
        }

        $order->unsetRelation('lines');
    }
}
