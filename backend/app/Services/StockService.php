<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every change of on-hand quantity.
 *
 * Each mutation is transactional, locks the stock row and writes an immutable
 * movement so the ledger can always explain the current quantity.
 */
class StockService
{
    public function increase(Product $product, Warehouse $warehouse, float $quantity, array $options = []): StockMovement
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($product, $warehouse, $quantity, $options) {
            $stock = $this->lockedStock($product, $warehouse);
            $before = (float) $stock->quantity;
            $stock->quantity = round($before + $quantity, 3);
            $stock->save();

            return $this->writeMovement($stock, StockMovementType::In, $quantity, $before, $options);
        });
    }

    /**
     * @param  array{reference?: Model|null, reason?: string|null, unit_cost?: float|null, user_id?: int|null, consume_reservation?: bool}  $options
     */
    public function decrease(Product $product, Warehouse $warehouse, float $quantity, array $options = []): StockMovement
    {
        $this->assertPositive($quantity);
        $consumeReservation = (bool) ($options['consume_reservation'] ?? false);

        return DB::transaction(function () use ($product, $warehouse, $quantity, $options, $consumeReservation) {
            $stock = $this->lockedStock($product, $warehouse);
            $before = (float) $stock->quantity;
            $reserved = (float) $stock->reserved_quantity;

            $available = $consumeReservation ? $before : $before - $reserved;

            if ($quantity > $available + 0.0001) {
                throw InsufficientStockException::for($product, $warehouse, $quantity, max(0, $available));
            }

            $stock->quantity = round($before - $quantity, 3);

            if ($consumeReservation) {
                $stock->reserved_quantity = round(max(0, $reserved - $quantity), 3);
            }

            $stock->save();

            return $this->writeMovement($stock, StockMovementType::Out, $quantity, $before, $options);
        });
    }

    /**
     * Sets the quantity to an absolute value (physical count).
     */
    public function adjust(Product $product, Warehouse $warehouse, float $newQuantity, array $options = []): ?StockMovement
    {
        if ($newQuantity < 0) {
            throw new BusinessException('La quantité ne peut pas être négative.');
        }

        return DB::transaction(function () use ($product, $warehouse, $newQuantity, $options) {
            $stock = $this->lockedStock($product, $warehouse);
            $before = (float) $stock->quantity;
            $delta = round($newQuantity - $before, 3);

            if (abs($delta) < 0.0001) {
                return null;
            }

            $stock->quantity = $newQuantity;
            $stock->save();

            return $this->writeMovement($stock, StockMovementType::Adjustment, abs($delta), $before, $options);
        });
    }

    /**
     * Books stock for a confirmed sales order without moving the physical
     * quantity yet.
     */
    public function reserve(Product $product, Warehouse $warehouse, float $quantity): void
    {
        $this->assertPositive($quantity);

        DB::transaction(function () use ($product, $warehouse, $quantity) {
            $stock = $this->lockedStock($product, $warehouse);
            $available = (float) $stock->quantity - (float) $stock->reserved_quantity;

            if ($quantity > $available + 0.0001) {
                throw InsufficientStockException::for($product, $warehouse, $quantity, max(0, $available));
            }

            $stock->reserved_quantity = round((float) $stock->reserved_quantity + $quantity, 3);
            $stock->save();
        });
    }

    public function release(Product $product, Warehouse $warehouse, float $quantity): void
    {
        $this->assertPositive($quantity);

        DB::transaction(function () use ($product, $warehouse, $quantity) {
            $stock = $this->lockedStock($product, $warehouse);
            $stock->reserved_quantity = round(max(0, (float) $stock->reserved_quantity - $quantity), 3);
            $stock->save();
        });
    }

    public function available(Product $product, Warehouse $warehouse): float
    {
        $stock = Stock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        return $stock ? round((float) $stock->quantity - (float) $stock->reserved_quantity, 3) : 0.0;
    }

    private function lockedStock(Product $product, Warehouse $warehouse): Stock
    {
        $stock = Stock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );

        return Stock::query()->whereKey($stock->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function writeMovement(Stock $stock, StockMovementType $type, float $quantity, float $before, array $options): StockMovement
    {
        /** @var Model|null $reference */
        $reference = $options['reference'] ?? null;

        return StockMovement::create([
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => (float) $stock->quantity,
            'unit_cost' => $options['unit_cost'] ?? null,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reason' => $options['reason'] ?? null,
            'user_id' => $options['user_id'] ?? Auth::id(),
        ]);
    }

    private function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new BusinessException('La quantité doit être supérieure à zéro.');
        }
    }
}
