<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\StockResource;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Current stock levels per product/warehouse.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Stock::query()->with(['product.category', 'warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->query('search').'%';
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', $term)->orWhere('sku', 'like', $term));
        }

        if ($request->boolean('in_stock')) {
            $query->where('quantity', '>', 0);
        }

        return StockResource::collection($query->orderByDesc('quantity')->paginate($this->perPage($request)));
    }

    /**
     * Immutable stock ledger.
     */
    public function movements(Request $request): AnonymousResourceCollection
    {
        $query = StockMovement::query()->with(['product', 'warehouse', 'user']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return StockMovementResource::collection(
            $query->orderByDesc('id')->paginate($this->perPage($request))
        );
    }

    /**
     * Manual stock operation: receipt, issue or physical count adjustment.
     */
    public function store(StoreStockMovementRequest $request): StockMovementResource
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($request->integer('product_id'));

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));

        $type = StockMovementType::from($request->validated('type'));
        $quantity = (float) $request->validated('quantity');

        $options = [
            'reason' => $request->validated('reason'),
            'unit_cost' => $request->validated('unit_cost'),
            'user_id' => $request->user()->id,
        ];

        $movement = match ($type) {
            StockMovementType::In, StockMovementType::TransferIn => $this->stock->increase($product, $warehouse, $quantity, $options),
            StockMovementType::Out, StockMovementType::TransferOut => $this->stock->decrease($product, $warehouse, $quantity, $options),
            StockMovementType::Adjustment => $this->stock->adjust($product, $warehouse, $quantity, $options),
        };

        if ($movement === null) {
            throw new BusinessException('Le stock est déjà à ce niveau : aucun ajustement nécessaire.');
        }

        return new StockMovementResource($movement->load(['product', 'warehouse', 'user']));
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
