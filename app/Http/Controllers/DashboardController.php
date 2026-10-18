<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponses;

    /**
     * GET /api/dashboard
     *
     * Con semestre actual: { "data": { semester, summary, courses, upcoming, recommendations_unread } }
     * Sin semestre actual: { "data": null, "message": "..." } (200, para que el front muestre el aviso)
     */
    public function index(Request $request, DashboardService $dashboard): JsonResponse
    {
        $data = $dashboard->forUser($request->user());

        if ($data === null) {
            return $this->ok(null, 'No tienes un semestre actual. Crea uno o márcalo como actual.');
        }

        return $this->ok($data);
    }
}
