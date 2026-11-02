<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\User;
use App\Support\LineTotals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly StockService $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $lines = $this->prepareLines($data['lines']);
            $totals = LineTotals::summarize($lines);

            $order = PurchaseOrder::create([
                'reference' => $this->references->next('purchase_order', 'PO'),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => $data['order_date'] ?? today(),
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id ?? Auth::id(),
            ] + $totals);

            $this->replaceLines($order, $lines);

            return $order->load(['supplier', 'warehouse', 'lines.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $order, array $data): PurchaseOrder
    {
        if (! $order->status->canBeEdited()) {
            throw InvalidStatusTransitionException::from('commande fournisseur', $order->status->label(), 'modifier');
        }

        return DB::transaction(function () use ($order, $data) {
            $order->fill(array_intersect_key($data, array_flip([
                'supplier_id', 'warehouse_id', 'order_date', 'expected_date', 'notes',
            ])));

            if (isset($data['lines'])) {
                $lines = $this->prepareLines($data['lines']);
                $this->replaceLines($order, $lines);
                $order->fill(LineTotals::summarize($lines));
            }

            $order->save();

            return $order->load(['supplier', 'warehouse', 'lines.product']);
        });
    }

    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw InvalidStatusTransitionException::from('commande fournisseur', $order->status->label(), 'confirmer');
        }

        if ($order->lines()->count() === 0) {
            throw new BusinessException('Une commande sans ligne ne peut pas être confirmée.');
        }

        $order->status = PurchaseOrderStatus::Confirmed;
        $order->save();

        return $order->fresh(['supplier', 'warehouse', 'lines.product']);
    }

    /**
     * Registers a goods receipt and feeds the warehouse.
     *
     * @param  array<int, array{quantity?: float|int|string, unit_price?: float|int|string}>  $lines  purchase_order_line_id => payload
     */
    public function receive(PurchaseOrder $order, array $lines = []): PurchaseOrder
    {
        $order->loadMissing('lines.product', 'warehouse');

        if (! $order->status->isReceivable()) {
            throw InvalidStatusTransitionException::from('commande fournisseur', $order->status->label(), 'réceptionner');
        }

        $received = 0.0;

        DB::transaction(function () use ($order, $lines, &$received) {
            foreach ($order->lines as $line) {
                $remaining = round((float) $line->quantity - (float) $line->received_quantity, 3);

                $requested = array_key_exists($line->id, $lines)
                    ? (float) ($lines[$line->id]['quantity'] ?? $remaining)
                    : $remaining;

                $quantity = min($requested, $remaining);

                if ($quantity <= 0) {
                    continue;
                }

                $unitCost = (float) ($lines[$line->id]['unit_price'] ?? $line->unit_price);

                $this->stock->increase($line->product, $order->warehouse, $quantity, [
                    'reference' => $order,
                    'unit_cost' => $unitCost,
                    'reason' => 'Réception '.$order->reference,
                ]);

                $this->updateAverageCost($line->product, $quantity, $unitCost);

                $line->received_quantity = round((float) $line->received_quantity + $quantity, 3);
                $line->save();

                $received += $quantity;
            }

            if ($received <= 0) {
                throw new BusinessException('Aucune quantité à réceptionner sur cette commande.');
            }

            $order->refresh()->load('lines');

            if ($order->isFullyReceived()) {
                $order->status = PurchaseOrderStatus::Received;
                $order->received_at = now();
            } else {
                $order->status = PurchaseOrderStatus::PartiallyReceived;
            }

            $order->save();
        });

        return $order->fresh(['supplier', 'warehouse', 'lines.product']);
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->status->isCancellable()) {
            throw InvalidStatusTransitionException::from('commande fournisseur', $order->status->label(), 'annuler');
        }

        $order->status = PurchaseOrderStatus::Cancelled;
        $order->save();

        return $order->fresh(['supplier', 'warehouse', 'lines.product']);
    }

    /**
     * Weighted average cost across all warehouses so margin reports stay sane.
     */
    private function updateAverageCost(Product $product, float $receivedQuantity, float $unitCost): void
    {
        $totalQuantity = (float) Stock::query()->where('product_id', $product->id)->sum('quantity');
        $previousQuantity = max(0, $totalQuantity - $receivedQuantity);

        $previousValue = $previousQuantity * (float) $product->cost_price;
        $newValue = $previousValue + $receivedQuantity * $unitCost;

        $product->cost_price = $totalQuantity > 0 ? round($newValue / $totalQuantity, 2) : $unitCost;
        $product->purchase_price = round($unitCost, 2);
        $product->save();
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
            $unitPrice = (float) ($line['unit_price'] ?? $product->purchase_price);
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
    private function replaceLines(PurchaseOrder $order, array $lines): void
    {
        $order->lines()->delete();

        foreach ($lines as $line) {
            $order->lines()->create($line);
        }

        $order->unsetRelation('lines');
    }
}
