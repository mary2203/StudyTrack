-- =====================================================================
-- StudyTrack · Migración manual: activities.user_id + SP corregidos
-- Para quienes YA importaron study_track.sql antes de este cambio.
-- Quien importe el study_track.sql actualizado NO necesita este script.
-- EJECUTAR UNA SOLA VEZ (en phpMyAdmin: base study_track -> pestaña SQL).
-- =====================================================================

-- 1) Agregar la columna (nullable mientras se rellena)
ALTER TABLE `activities`
  ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `id`;

-- 2) Rellenar: actividades con curso -> dueño del curso
UPDATE `activities` a
JOIN `courses` c ON a.course_id = c.id
JOIN `academic_semesters` s ON c.semester_id = s.id
SET a.user_id = s.user_id;

-- 3) Rellenar: actividades sin curso -> usuario 1 (datos de prueba del seed)
--    Si ya crearon actividades sin curso con otros usuarios, corríjanlas a mano.
UPDATE `activities` SET `user_id` = 1 WHERE `user_id` IS NULL;

-- 4) Volver obligatoria la columna, con índice y llave foránea
ALTER TABLE `activities`
  MODIFY `user_id` BIGINT UNSIGNED NOT NULL,
  ADD KEY `user_id` (`user_id`),
  ADD CONSTRAINT `activities_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 5) Recrear los procedimientos corregidos
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sync_calendar_priorities`$$
CREATE PROCEDURE `sp_sync_calendar_priorities` (IN `p_user_id` BIGINT UNSIGNED)
BEGIN
    -- Quitar eventos de actividades borradas lógicamente
    DELETE ce
    FROM calendar_events ce
    JOIN activities a ON a.id = ce.activity_id
    WHERE ce.user_id = p_user_id
      AND a.deleted_at IS NOT NULL;

    INSERT INTO calendar_events (user_id, course_id, activity_id, title, event_datetime, calculated_weight, priority_level_id)
    SELECT
        p_user_id,
        a.course_id,
        a.id,
        a.title,
        a.due_datetime,
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
END$$

DROP PROCEDURE IF EXISTS `sp_generate_study_recommendations`$$
CREATE PROCEDURE `sp_generate_study_recommendations` (IN `p_user_id` BIGINT UNSIGNED)
BEGIN
    DELETE FROM system_recommendations
    WHERE user_id = p_user_id AND is_read = FALSE;

    -- Materia en riesgo crítico
    INSERT INTO system_recommendations (user_id, course_id, title, message, urgency_level)
    SELECT
        p_user_id,
        c.id,
        CONCAT('Atención Urgente: ', c.name),
        CONCAT('Tu promedio actual es de ', c.current_average, ' pts y tienes una actividad programada en menos de 72 horas. Dedica tiempo de estudio hoy.'),
        'critical'
    FROM courses c
    JOIN academic_semesters s ON c.semester_id = s.id
    WHERE s.user_id = p_user_id
      AND c.priority_level_id = 1
      AND c.deleted_at IS NULL;

    -- Sobrecarga académica detectada (Predictivo)
    INSERT INTO system_recommendations (user_id, course_id, title, message, urgency_level)
    SELECT
        p_user_id,
        NULL,
        'Alerta de Sobrecarga Académica',
        CONCAT('Tienes ', COUNT(a.id), ' actividades concentradas en los próximos 7 días. Planifica tu semana.'),
        'high'
    FROM activities a
    WHERE a.user_id = p_user_id
      AND a.status IN ('pending', 'in_progress')
      AND a.due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND a.deleted_at IS NULL
    HAVING COUNT(a.id) >= 4;

    SELECT * FROM system_recommendations WHERE user_id = p_user_id ORDER BY created_at DESC;
END$$

DELIMITER ;
