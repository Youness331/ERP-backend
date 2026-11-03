<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankLineRequest;
use App\Http\Resources\BankLineResource;
use App\Http\Resources\PaymentResource;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Services\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BankLineController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly BankReconciliationService $reconciliation,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BankStatementLine::query()
            ->with(['payment', 'creator:id,name'])
            ->status($request->query('status'));

        if ($request->filled('direction')) {
            $query->where('direction', $request->query('direction'));
        }

        if ($request->filled('from')) {
            $query->whereDate('value_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('value_date', '<=', $request->query('to'));
        }

        return BankLineResource::collection(
            $query->orderByDesc('value_date')->orderByDesc('id')->paginate($this->perPage($request))
        );
    }

    public function store(StoreBankLineRequest $request): BankLineResource
    {
        $line = $this->reconciliation->create($request->validated(), $request->user());

        return new BankLineResource($line);
    }

    public function show(BankStatementLine $bankLine): BankLineResource
    {
        return new BankLineResource($bankLine->load(['payment', 'creator']));
    }

    public function match(Request $request, BankStatementLine $bankLine): BankLineResource
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
        ]);

        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($validated['payment_id']);

        return new BankLineResource($this->reconciliation->match($bankLine, $payment));
    }

    public function unmatch(BankStatementLine $bankLine): BankLineResource
    {
        return new BankLineResource($this->reconciliation->unmatch($bankLine));
    }

    public function suggestions(BankStatementLine $bankLine): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->reconciliation->suggestions($bankLine));
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'unmatched' => BankStatementLine::query()->unmatched()->count(),
                'matched' => BankStatementLine::query()->where('status', 'matched')->count(),
                'ignored' => BankStatementLine::query()->where('status', 'ignored')->count(),
            ],
        ]);
    }
}
