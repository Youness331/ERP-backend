<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCreditNoteRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    use Paginates;

    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::query()
            ->with(['client:id,name,code'])
            ->search($request->query('search'))
            ->status($request->query('status'));

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->boolean('outstanding')) {
            $query->outstanding();
        }

        if ($request->filled('from')) {
            $query->whereDate('issue_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('issue_date', '<=', $request->query('to'));
        }

        return InvoiceResource::collection($query->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function store(StoreInvoiceRequest $request): InvoiceResource
    {
        $invoice = $this->invoices->create($request->validated(), $request->user());

        return new InvoiceResource($invoice);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource(
            $invoice->load(['client', 'salesOrder', 'lines.product', 'payments.creator', 'creator'])
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->update($invoice, $request->validated()));
    }

    public function destroy(Invoice $invoice): Response
    {
        if (! $invoice->status->isEditable()) {
            throw new BusinessException('Seule une facture en brouillon peut être supprimée.');
        }

        $invoice->delete();

        return response()->noContent();
    }

    public function send(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->send($invoice));
    }

    public function cancel(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->cancel($invoice));
    }

    public function storePayment(StorePaymentRequest $request, Invoice $invoice): PaymentResource
    {
        $payment = $this->invoices->recordPayment($invoice, $request->validated(), $request->user());

        return new PaymentResource($payment);
    }

    /**
     * Renders the invoice as a printable PDF document.
     */
    public function pdf(Invoice $invoice): Response
    {
        $invoice->load(['client', 'lines.product']);

        $pdf = Pdf::loadView('documents.invoice', [
            'invoice' => $invoice,
            'company' => config('erp.company'),
        ])->setPaper('a4');

        return $pdf->download($invoice->reference.'.pdf');
    }

    /**
     * Issues a credit note against the invoice.
     */
    public function creditNote(StoreCreditNoteRequest $request, Invoice $invoice): InvoiceResource
    {
        $credit = $this->invoices->creditNoteFrom(
            $invoice,
            $request->validated('lines'),
            $request->user(),
        );

        return new InvoiceResource($credit);
    }

    /**
     * Queues the invoice email with the PDF attached.
     */
    public function email(Invoice $invoice): JsonResponse
    {
        $invoice->loadMissing('client');

        if (blank($invoice->client?->email)) {
            throw new BusinessException('Ce client n\'a pas d\'adresse email renseignée.');
        }

        SendInvoiceEmail::dispatch($invoice->id);

        return response()->json(['message' => 'Facture mise en file d\'envoi.'], 202);
    }
}
