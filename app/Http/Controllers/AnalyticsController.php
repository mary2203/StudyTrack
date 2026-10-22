<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Services\AnalyticsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponses;

    /**
     * GET /api/analytics/{level}
     */
    public function show(
        Request $request,
        string $level,
        AnalyticsReportService $analytics
    ): JsonResponse {
        return $this->ok($analytics->forUser($request->user(), $level));
    }
}
