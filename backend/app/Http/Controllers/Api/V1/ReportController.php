<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function sales(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => [
                'period' => $this->periodPayload($from, $to),
                ...$this->reports->sales($from, $to),
            ],
        ]);
    }

    public function purchases(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => [
                'period' => $this->periodPayload($from, $to),
                ...$this->reports->purchases($from, $to),
            ],
        ]);
    }

    public function stockValuation(): JsonResponse
    {
        return response()->json(['data' => $this->reports->stockValuation()]);
    }

    public function receivablesAging(): JsonResponse
    {
        return response()->json(['data' => $this->reports->receivablesAging()]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : CarbonImmutable::now()->startOfMonth();

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now()->endOfMonth();

        return [$from, $to];
    }

    /**
     * @return array{from: string, to: string}
     */
    private function periodPayload(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }
}
