<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PurchaseOrderController extends Controller
{
    use Paginates;

    public function __construct(private readonly PurchaseOrderService $purchaseOrders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::query()
            ->with(['supplier:id,name,code', 'warehouse:id,name,code'])
            ->search($request->query('search'))
            ->status($request->query('status'));

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->query('to'));
        }

        return PurchaseOrderResource::collection($query->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function store(StorePurchaseOrderRequest $request): PurchaseOrderResource
    {
        $order = $this->purchaseOrders->create($request->validated(), $request->user());

        return new PurchaseOrderResource($order);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource(
            $purchaseOrder->load(['supplier', 'warehouse', 'lines.product', 'creator', 'payments.creator'])
        );
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $order = $this->purchaseOrders->update($purchaseOrder, $request->validated());

        return new PurchaseOrderResource($order);
    }

    public function destroy(PurchaseOrder $purchaseOrder): Response
    {
        if (! $purchaseOrder->status->canBeEdited()) {
            throw new BusinessException('Seule une commande en brouillon peut être supprimée.');
        }

        $purchaseOrder->delete();

        return response()->noContent();
    }

    public function confirm(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrders->confirm($purchaseOrder));
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $order = $this->purchaseOrders->receive($purchaseOrder, $request->validated('lines') ?? []);

        return new PurchaseOrderResource($order);
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrders->cancel($purchaseOrder));
    }

    public function storePayment(StorePaymentRequest $request, PurchaseOrder $purchaseOrder): PaymentResource
    {
        $payment = $this->purchaseOrders->recordPayment(
            $purchaseOrder,
            $request->validated(),
            $request->user(),
        );

        return new PaymentResource($payment);
    }
}
