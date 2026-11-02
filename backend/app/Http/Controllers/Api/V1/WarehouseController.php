<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Services\ReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function __construct(private readonly ReferenceGenerator $references) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Warehouse::query()->withCount('stocks');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->query('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term));
        }

        return WarehouseResource::collection($query->orderBy('name')->paginate($this->perPage($request)));
    }

    public function store(StoreWarehouseRequest $request): WarehouseResource
    {
        $data = $request->validated();
        $data['code'] ??= $this->references->next('warehouse', 'WH');

        $warehouse = DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return Warehouse::create($data);
        });

        return new WarehouseResource($warehouse);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse->loadCount('stocks'));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        DB::transaction(function () use ($request, $warehouse) {
            if ($request->boolean('is_default')) {
                Warehouse::query()->whereKeyNot($warehouse->id)->update(['is_default' => false]);
            }

            $warehouse->update($request->validated());
        });

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse): Response
    {
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            throw new BusinessException('Impossible de supprimer un entrepôt contenant du stock.');
        }

        $warehouse->delete();

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
