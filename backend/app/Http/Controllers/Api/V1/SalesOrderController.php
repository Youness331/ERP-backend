<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliverSalesOrderRequest;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SalesOrderController extends Controller
{
    use Paginates;

    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SalesOrder::query()
            ->with(['client:id,name,code', 'warehouse:id,name,code'])
            ->search($request->query('search'))
            ->status($request->query('status'));

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->query('to'));
        }

        return SalesOrderResource::collection($query->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function store(StoreSalesOrderRequest $request): SalesOrderResource
    {
        $order = $this->salesOrders->create($request->validated(), $request->user());

        return new SalesOrderResource($order);
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource(
            $salesOrder->load(['client', 'warehouse', 'lines.product', 'invoices', 'creator'])
        );
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $order = $this->salesOrders->update($salesOrder, $request->validated());

        return new SalesOrderResource($order);
    }

    public function destroy(SalesOrder $salesOrder): Response
    {
        if (! $salesOrder->status->canBeEdited()) {
            throw new BusinessException('Seule une commande en brouillon peut être supprimée.');
        }

        $salesOrder->delete();

        return response()->noContent();
    }

    public function confirm(SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrders->confirm($salesOrder));
    }

    public function deliver(DeliverSalesOrderRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $order = $this->salesOrders->deliver($salesOrder, $request->validated('quantities') ?? []);

        return new SalesOrderResource($order);
    }

    public function cancel(SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrders->cancel($salesOrder));
    }

    public function invoice(Request $request, SalesOrder $salesOrder): InvoiceResource
    {
        $invoice = $this->salesOrders->invoice($salesOrder, $request->user());

        return new InvoiceResource($invoice);
    }

    /**
     * Renders the delivery note (bon de livraison) as a PDF.
     */
    public function deliveryNote(SalesOrder $salesOrder): Response
    {
        $salesOrder->load(['client', 'lines.product']);

        $pdf = Pdf::loadView('documents.delivery-note', [
            'order' => $salesOrder,
            'company' => config('erp.company'),
        ])->setPaper('a4');

        return $pdf->download($salesOrder->reference.'.pdf');
    }
}
