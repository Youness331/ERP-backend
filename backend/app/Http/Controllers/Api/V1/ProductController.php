<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    use Paginates;

    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with('category')
            ->withSum('stocks as stocks_sum_quantity', 'quantity')
            ->withSum('stocks as stocks_sum_reserved_quantity', 'reserved_quantity')
            ->search($request->query('search'));

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sort = in_array($request->query('sort'), ['name', 'sku', 'sale_price', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return ProductResource::collection(
            $query->orderBy($sort, $direction)->paginate($this->perPage($request))
        );
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $data = $request->validated();
        $openingStock = $data['opening_stock'] ?? [];
        unset($data['opening_stock']);

        $product = Product::create($data);

        foreach ($openingStock as $entry) {
            $quantity = (float) $entry['quantity'];

            if ($quantity <= 0) {
                continue;
            }

            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->findOrFail($entry['warehouse_id']);

            $this->stock->increase($product, $warehouse, $quantity, [
                'reason' => 'Stock initial',
                'unit_cost' => (float) $product->purchase_price,
            ]);
        }

        return new ProductResource($this->loadStockAggregates($product->load('category')));
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'stocks.warehouse']);

        return new ProductResource($this->loadStockAggregates($product));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($this->loadStockAggregates($product->load('category')));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    private function loadStockAggregates(Product $product): Product
    {
        return $product
            ->loadSum('stocks as stocks_sum_quantity', 'quantity')
            ->loadSum('stocks as stocks_sum_reserved_quantity', 'reserved_quantity');
    }
}
