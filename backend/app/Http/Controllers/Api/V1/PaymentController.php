<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    use Paginates;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::query()->with(['creator']);

        if ($request->filled('direction')) {
            $query->where('direction', $request->query('direction'));
        }

        if ($request->filled('method')) {
            $query->where('method', $request->query('method'));
        }

        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->query('to'));
        }

        if ($request->filled('payable_type')) {
            $query->where('payable_type', $request->query('payable_type'));
        }

        if ($request->filled('payable_id')) {
            $query->where('payable_id', $request->integer('payable_id'));
        }

        return PaymentResource::collection(
            $query->orderByDesc('payment_date')->orderByDesc('id')->paginate($this->perPage($request))
        );
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($payment->load(['creator', 'payable']));
    }
}
