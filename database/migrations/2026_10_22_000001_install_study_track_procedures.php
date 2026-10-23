<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_calculate_course_average');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE sp_calculate_course_average(IN p_course_id BIGINT UNSIGNED)
            BEGIN
                DECLARE v_avg DECIMAL(5,2) DEFAULT 0.00;
                DECLARE v_priority_id INT DEFAULT 3;
                DECLARE v_has_urgent_activities INT DEFAULT 0;

                SELECT COALESCE((SUM(points_obtained) / NULLIF(SUM(points_possible), 0)) * 100, 0)
                INTO v_avg
                FROM activities
                WHERE course_id = p_course_id
                  AND is_graded = TRUE
                  AND points_possible > 0
                  AND points_obtained IS NOT NULL
                  AND deleted_at IS NULL;

                SELECT COUNT(*) INTO v_has_urgent_activities
                FROM activities
                WHERE course_id = p_course_id
                  AND status IN ('pending', 'in_progress')
                  AND due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
                  AND deleted_at IS NULL;

                IF v_avg < 61.00 AND v_has_urgent_activities > 0 THEN
                    SET v_priority_id = 1;
                ELSEIF v_avg < 75.00 OR v_has_urgent_activities > 0 THEN
                    SET v_priority_id = 2;
                ELSE
                    SET v_priority_id = 3;
                END IF;

                UPDATE courses
                SET current_average = v_avg,
                    priority_level_id = v_priority_id,
                    updated_at = NOW()
                WHERE id = p_course_id;

                SELECT p_course_id AS course_id, v_avg AS calculated_average, v_priority_id AS priority_level_id;
            END
            SQL);

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_generate_study_recommendations');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE sp_generate_study_recommendations(IN p_user_id BIGINT UNSIGNED)
            BEGIN
                DELETE FROM system_recommendations
                WHERE user_id = p_user_id AND is_read = FALSE;

                INSERT INTO system_recommendations (user_id, course_id, title, message, urgency_level)
                SELECT p_user_id, c.id,
                       CONCAT('Atención Urgente: ', c.name),
                       CONCAT('Tu promedio actual es de ', c.current_average,
                              ' pts y tienes una actividad programada en menos de 72 horas. Dedica tiempo de estudio hoy.'),
                       'critical'
                FROM courses c
                JOIN academic_semesters s ON c.semester_id = s.id
                WHERE s.user_id = p_user_id
                  AND c.priority_level_id = 1
                  AND c.deleted_at IS NULL;

                INSERT INTO system_recommendations (user_id, course_id, title, message, urgency_level)
                SELECT p_user_id, NULL,
                       'Alerta de Sobrecarga Académica',
                       CONCAT('Tienes ', COUNT(a.id),
                              ' actividades concentradas en los próximos 7 días. Planifica tu semana.'),
                       'high'
                FROM activities a
                WHERE a.user_id = p_user_id
                  AND a.status IN ('pending', 'in_progress')
                  AND a.due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                  AND a.deleted_at IS NULL
                HAVING COUNT(a.id) >= 4;

                SELECT * FROM system_recommendations
                WHERE user_id = p_user_id
                ORDER BY created_at DESC;
            END
            SQL);

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_sync_calendar_priorities');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE sp_sync_calendar_priorities(IN p_user_id BIGINT UNSIGNED)
            BEGIN
                DELETE ce
                FROM calendar_events ce
                JOIN activities a ON a.id = ce.activity_id
                WHERE ce.user_id = p_user_id
                  AND a.deleted_at IS NOT NULL;

                INSERT INTO calendar_events (
                    user_id, course_id, activity_id, title, event_datetime,
                    calculated_weight, priority_level_id
                )
                SELECT p_user_id, a.course_id, a.id, a.title, a.due_datetime,
                       CASE
                           WHEN a.is_graded = FALSE THEN 10
                           ELSE LEAST(100, ROUND(a.points_possible * COALESCE(pr.weight_multiplier, 1.0)))
                       END,
                       COALESCE(c.priority_level_id, a.priority_level_id, 4)
                FROM activities a
                LEFT JOIN courses c ON a.course_id = c.id
                LEFT JOIN priority_rules pr ON c.priority_level_id = pr.id
                WHERE a.user_id = p_user_id
                  AND a.deleted_at IS NULL
                ON DUPLICATE KEY UPDATE
                    course_id = VALUES(course_id),
                    title = VALUES(title),
                    event_datetime = VALUES(event_datetime),
                    calculated_weight = VALUES(calculated_weight),
                    priority_level_id = VALUES(priority_level_id);
            END
            SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_sync_calendar_priorities');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_generate_study_recommendations');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_calculate_course_average');
    }
};
