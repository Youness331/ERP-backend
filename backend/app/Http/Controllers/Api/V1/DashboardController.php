<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'company' => config('erp.company'),
                'currency' => config('erp.currency'),
                ...$this->dashboard->summary(),
            ],
        ]);
    }
}
