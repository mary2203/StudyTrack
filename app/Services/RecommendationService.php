<?php

namespace App\Services;

use App\Models\SystemRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consultas de recomendaciones del tablón.
 *
 * Problema que resuelve: sp_generate_study_recommendations borra solo las NO leídas
 * y vuelve a crear las que siguen vigentes. Sin este filtro, una alerta marcada como
 * leída reaparecería sin leer en el siguiente recálculo (que ocurre en cada carga
 * del dashboard).
 *
 * Regla: una recomendación no leída se oculta si el usuario ya marcó como leída otra
 * igual (mismo curso y mismo título) generada en las últimas 24 horas. Pasado ese
 * tiempo, si la condición sigue vigente, vuelve a mostrarse como recordatorio.
 *
 * La tabla no tiene `read_at`, por eso la ventana se mide con `created_at` de la
 * recomendación leída. Se usa NOW() de MySQL (igual que los SP) para no depender de
 * la zona horaria de Laravel.
 */
class RecommendationService
{
    public const HIDE_AFTER_READ_HOURS = 24;

    /** Recomendaciones no leídas y vigentes del usuario. */
    public function activeQuery(User $user): Builder
    {
        return SystemRecommendation::query()
            ->where('system_recommendations.user_id', $user->id)
            ->where('system_recommendations.is_read', false)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('system_recommendations as dismissed')
                    ->whereColumn('dismissed.user_id', 'system_recommendations.user_id')
                    ->whereColumn('dismissed.title', 'system_recommendations.title')
                    ->whereRaw('dismissed.course_id <=> system_recommendations.course_id')
                    ->where('dismissed.is_read', true)
                    ->whereRaw(
                        'dismissed.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
                        [self::HIDE_AFTER_READ_HOURS]
                    );
            });
    }

    /** Recomendaciones ya leídas (historial). */
    public function readQuery(User $user): Builder
    {
        return SystemRecommendation::query()
            ->where('user_id', $user->id)
            ->where('is_read', true);
    }
}
