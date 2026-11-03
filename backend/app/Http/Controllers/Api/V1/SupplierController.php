<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\ReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SupplierController extends Controller
{
    use Paginates;

    public function __construct(private readonly ReferenceGenerator $references) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::query()
            ->withCount('purchaseOrders')
            ->search($request->query('search'));

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sort = in_array($request->query('sort'), ['name', 'code', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return SupplierResource::collection(
            $query->orderBy($sort, $direction)->paginate($this->perPage($request))
        );
    }

    public function store(StoreSupplierRequest $request): SupplierResource
    {
        $data = $request->validated();
        $data['code'] ??= $this->references->next('supplier', 'SUP');
        $data['created_by'] = $request->user()->id;

        $supplier = Supplier::create($data);

        return new SupplierResource($supplier);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier->loadCount('purchaseOrders'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): Response
    {
        $hasOpenOrders = $supplier->purchaseOrders()->open()->exists();

        if ($hasOpenOrders) {
            throw new BusinessException('Impossible de supprimer un fournisseur ayant des commandes en cours.');
        }

        $supplier->delete();

        return response()->noContent();
    }
}
