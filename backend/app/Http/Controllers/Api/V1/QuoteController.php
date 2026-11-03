<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuoteController extends Controller
{
    use Paginates;

    public function __construct(private readonly QuoteService $quotes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Quote::query()
            ->with(['client:id,name,code', 'warehouse:id,name,code'])
            ->search($request->query('search'))
            ->status($request->query('status'));

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('quote_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('quote_date', '<=', $request->query('to'));
        }

        return QuoteResource::collection($query->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function store(StoreQuoteRequest $request): QuoteResource
    {
        return new QuoteResource($this->quotes->create($request->validated(), $request->user()));
    }

    public function show(Quote $quote): QuoteResource
    {
        return new QuoteResource(
            $quote->load(['client', 'warehouse', 'lines.product', 'salesOrder', 'creator'])
        );
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): QuoteResource
    {
        return new QuoteResource($this->quotes->update($quote, $request->validated()));
    }

    public function destroy(Quote $quote): Response
    {
        if (! $quote->status->canBeEdited()) {
            throw new BusinessException('Seul un devis en brouillon peut être supprimé.');
        }

        $quote->delete();

        return response()->noContent();
    }

    public function send(Quote $quote): QuoteResource
    {
        return new QuoteResource($this->quotes->send($quote));
    }

    public function accept(Quote $quote): QuoteResource
    {
        return new QuoteResource($this->quotes->accept($quote));
    }

    public function reject(Quote $quote): QuoteResource
    {
        return new QuoteResource($this->quotes->reject($quote));
    }

    public function convert(Request $request, Quote $quote): SalesOrderResource
    {
        return new SalesOrderResource($this->quotes->convert($quote, $request->user()));
    }
}
