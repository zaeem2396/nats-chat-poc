<?php

namespace App\Http\Controllers;

use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function __invoke(MetricsService $metrics): JsonResponse
    {
        return response()->json($metrics->get());
    }
}
