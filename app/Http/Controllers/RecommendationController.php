<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Resources\RecommendationResource;
use App\Models\SystemRecommendation;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecommendationController extends Controller
{
    use ApiResponses;

    /**
     * GET /api/recommendations?status=unread|read   (por defecto: unread)
     *
     * Solo lectura: no recalcula nada. Las recomendaciones se generan al cargar el
     * dashboard o cuando cambia una actividad (AnalyticsService).
     */
    public function index(Request $request, RecommendationService $recommendations)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:unread,read'],
        ]);

        $user = $request->user();

        $query = ($validated['status'] ?? 'unread') === 'read'
            ? $recommendations->readQuery($user)
            : $recommendations->activeQuery($user);

        return RecommendationResource::collection(
            $query->with('course:id,name,color_hex')
                ->orderByRaw("FIELD(urgency_level, 'critical', 'high', 'medium', 'low')")
                ->orderByDesc('created_at')
                ->get()
        );
    }

    /** PATCH /api/recommendations/{recommendation}/read */
    public function markAsRead(SystemRecommendation $recommendation): JsonResponse
    {
        Gate::authorize('update', $recommendation);

        // La tabla no tiene updated_at: se actualiza directo para evitar que Eloquent lo agregue
        DB::table('system_recommendations')
            ->where('id', $recommendation->id)
            ->update(['is_read' => true]);

        return $this->ok(
            new RecommendationResource($recommendation->refresh()->load('course:id,name,color_hex')),
            'Recomendación marcada como leída.'
        );
    }
}
