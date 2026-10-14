-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-10-2026 a las 03:50:52
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `study_track`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_course_average` (IN `p_course_id` BIGINT UNSIGNED)   BEGIN
    DECLARE v_avg DECIMAL(5,2) DEFAULT 0.00;
    DECLARE v_priority_id INT DEFAULT 3;
    DECLARE v_has_urgent_activities INT DEFAULT 0;

    -- Solo calcular sobre actividades calificables que tengan nota
    SELECT COALESCE(
        (SUM(points_obtained) / NULLIF(SUM(points_possible), 0)) * 100, 0
    ) INTO v_avg
    FROM activities
    WHERE course_id = p_course_id 
      AND is_graded = TRUE
      AND points_possible > 0
      AND points_obtained IS NOT NULL 
      AND deleted_at IS NULL;

    -- Verificar entregas urgentes en los próximos 3 días
    SELECT COUNT(*) INTO v_has_urgent_activities
    FROM activities
    WHERE course_id = p_course_id
      AND status IN ('pending', 'in_progress')
      AND due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
      AND deleted_at IS NULL;

    IF v_avg < 61.00 AND v_has_urgent_activities > 0 THEN
        SET v_priority_id = 1; -- CRITICAL_RED
    ELSEIF v_avg < 75.00 OR v_has_urgent_activities > 0 THEN
        SET v_priority_id = 2; -- WARNING_YELLOW
    ELSE
        SET v_priority_id = 3; -- SAFE_GREEN
    END IF;

    UPDATE courses 
    SET current_average = v_avg,
        priority_level_id = v_priority_id,
        updated_at = NOW()
    WHERE id = p_course_id;

    SELECT p_course_id AS course_id, v_avg AS calculated_average, v_priority_id AS priority_level_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_study_recommendations` (IN `p_user_id` BIGINT UNSIGNED)   BEGIN
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
    LEFT JOIN courses c ON a.course_id = c.id
    LEFT JOIN academic_semesters s ON c.semester_id = s.id
    WHERE (s.user_id = p_user_id OR a.course_id IS NULL)
      AND a.status IN ('pending', 'in_progress')
      AND a.due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND a.deleted_at IS NULL
    HAVING COUNT(a.id) >= 4;

    SELECT * FROM system_recommendations WHERE user_id = p_user_id ORDER BY created_at DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_sync_calendar_priorities` (IN `p_user_id` BIGINT UNSIGNED)   BEGIN
    INSERT INTO calendar_events (user_id, course_id, activity_id, title, event_datetime, calculated_weight, priority_level_id)
    SELECT 
        p_user_id,
        c.id,
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
    LEFT JOIN academic_semesters s ON c.semester_id = s.id
    LEFT JOIN priority_rules pr ON c.priority_level_id = pr.id
    WHERE (s.user_id = p_user_id OR a.course_id IS NULL)
      AND a.deleted_at IS NULL
    ON DUPLICATE KEY UPDATE 
        event_datetime = VALUES(event_datetime),
        calculated_weight = VALUES(calculated_weight),
        priority_level_id = VALUES(priority_level_id);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `academic_semesters`
--

CREATE TABLE `academic_semesters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `academic_semesters`
--

INSERT INTO `academic_semesters` (`id`, `user_id`, `name`, `start_date`, `end_date`, `is_current`, `created_at`, `updated_at`) VALUES
(1, 1, 'Segundo Semestre 2026', '2026-07-01', '2026-11-30', 1, '2026-10-13 01:16:55', '2026-10-13 01:16:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activities`
--

CREATE TABLE `activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `activity_type` enum('task','exam','project','reading','administrative','meeting') DEFAULT 'task',
  `is_graded` tinyint(1) DEFAULT 1,
  `points_possible` decimal(5,2) DEFAULT 0.00,
  `points_obtained` decimal(5,2) DEFAULT NULL,
  `due_datetime` datetime NOT NULL,
  `estimated_minutes` int(11) DEFAULT 45,
  `status` enum('pending','in_progress','completed','overdue') DEFAULT 'pending',
  `priority_level_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `activities`
--

INSERT INTO `activities` (`id`, `course_id`, `title`, `description`, `activity_type`, `is_graded`, `points_possible`, `points_obtained`, `due_datetime`, `estimated_minutes`, `status`, `priority_level_id`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Parcial 1: Álgebra', 'Evaluación de unidad', 'exam', 1, 25.00, 10.00, '2026-10-07 19:16:55', 90, 'completed', NULL, NULL, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL),
(2, 1, 'Hoja de Trabajo: Ecuaciones', 'Ejercicios cap. 4', 'task', 1, 15.00, NULL, '2026-10-14 19:16:55', 45, 'pending', NULL, NULL, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL),
(3, 2, 'Ensayo Revolución Industrial', 'Mínimo 3 páginas', 'task', 1, 20.00, 20.00, '2026-10-10 19:16:55', 60, 'completed', NULL, NULL, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL),
(4, 2, 'Lectura Capítulo 5', 'Control de lectura', 'reading', 1, 10.00, NULL, '2026-10-22 19:16:55', 30, 'pending', NULL, NULL, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL),
(5, NULL, 'Llenar formulario de solicitud de graduación', 'Subir papelería a secretaría académica', 'administrative', 0, 0.00, NULL, '2026-10-13 19:16:55', 20, 'pending', NULL, NULL, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL),
(6, NULL, 'Reunión con Asesor de Graduación', 'Revisión del capítulo metodológico', 'meeting', 0, 0.00, NULL, '2026-10-15 19:16:55', 60, 'pending', NULL, NULL, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_name` varchar(50) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(25) NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `activity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_datetime` datetime NOT NULL,
  `calculated_weight` int(11) DEFAULT 10,
  `priority_level_id` int(11) DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `calendar_events`
--

INSERT INTO `calendar_events` (`id`, `user_id`, `course_id`, `activity_id`, `title`, `description`, `event_datetime`, `calculated_weight`, `priority_level_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'Parcial 1: Álgebra', NULL, '2026-10-07 19:16:55', 63, 1, '2026-10-13 01:16:55', '2026-10-13 01:16:55'),
(2, 1, 1, 2, 'Hoja de Trabajo: Ecuaciones', NULL, '2026-10-14 19:16:55', 38, 1, '2026-10-13 01:16:55', '2026-10-13 01:16:55'),
(3, 1, 2, 3, 'Ensayo Revolución Industrial', NULL, '2026-10-10 19:16:55', 20, 3, '2026-10-13 01:16:55', '2026-10-13 01:16:55'),
(4, 1, 2, 4, 'Lectura Capítulo 5', NULL, '2026-10-22 19:16:55', 10, 3, '2026-10-13 01:16:55', '2026-10-13 01:16:55'),
(5, 1, NULL, 5, 'Llenar formulario de solicitud de graduación', NULL, '2026-10-13 19:16:55', 10, 4, '2026-10-13 01:16:55', '2026-10-13 01:16:55'),
(6, 1, NULL, 6, 'Reunión con Asesor de Graduación', NULL, '2026-10-15 19:16:55', 10, 4, '2026-10-13 01:16:55', '2026-10-13 01:16:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `color_hex` varchar(7) DEFAULT '#6C5CE7',
  `target_grade` decimal(5,2) DEFAULT 61.00,
  `current_average` decimal(5,2) DEFAULT 0.00,
  `priority_level_id` int(11) DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `courses`
--

INSERT INTO `courses` (`id`, `semester_id`, `name`, `code`, `color_hex`, `target_grade`, `current_average`, `priority_level_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Basic Mathematic', 'MAT-101', '#FF4757', 61.00, 40.00, 1, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL),
(2, 1, 'World History', 'HIS-201', '#2ED573', 61.00, 100.00, 3, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL),
(3, 1, 'Science', 'SCI-301', '#1E90FF', 61.00, 0.00, 3, '2026-10-13 01:16:55', '2026-10-13 01:16:55', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `priority_rules`
--

CREATE TABLE `priority_rules` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color_hex` varchar(7) NOT NULL,
  `badge_label` varchar(30) NOT NULL,
  `min_score_threshold` decimal(5,2) DEFAULT NULL,
  `max_score_threshold` decimal(5,2) DEFAULT NULL,
  `weight_multiplier` decimal(3,2) DEFAULT 1.00,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `priority_rules`
--

INSERT INTO `priority_rules` (`id`, `code`, `name`, `color_hex`, `badge_label`, `min_score_threshold`, `max_score_threshold`, `weight_multiplier`, `description`) VALUES
(1, 'CRITICAL_RED', 'Prioridad Crítica', '#FF4757', 'Urgente', 0.00, 60.99, 2.50, 'Promedio bajo y entregas inmediatas'),
(2, 'WARNING_YELLOW', 'Prioridad Media', '#FFA502', 'Atención', 61.00, 75.00, 1.50, 'Promedio regular o entregas a mediano plazo'),
(3, 'SAFE_GREEN', 'Prioridad Baja', '#2ED573', 'Al día', 75.01, 100.00, 1.00, 'Buen rendimiento y sin entregas críticas'),
(4, 'INFO_BLUE', 'Informativo', '#1E90FF', 'Evento', NULL, NULL, 0.50, 'Actividades informativas o generales');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('VnOXYTrDgEgBrtoMYTSfvPXddY0e18wFrjBW0uvp', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUXFOdFN0UERnTHc0SzgzckhJRDQ1NVBUekllTmRmVDV4N3lpdnRwWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1791850802);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_recommendations`
--

CREATE TABLE `system_recommendations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `urgency_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `action_type` varchar(50) DEFAULT 'study_suggestion',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `system_recommendations`
--

INSERT INTO `system_recommendations` (`id`, `user_id`, `course_id`, `title`, `message`, `urgency_level`, `action_type`, `is_read`, `created_at`) VALUES
(1, 1, 1, 'Atención Urgente: Basic Mathematic', 'Tu promedio actual es de 40.00 pts y tienes una actividad programada en menos de 72 horas. Dedica tiempo de estudio hoy.', 'critical', 'study_suggestion', 0, '2026-10-13 01:16:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'María Pacheco', 'maria@upg.edu.gt', NULL, '$2y$12$e8gVlH6gWj8u0X8V2X1mE.1aO2fK7b0L1sQyW9k8v7z6y5x4w3v2u', NULL, '2026-10-13 01:16:55', '2026-10-13 01:16:55');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `academic_semesters`
--
ALTER TABLE `academic_semesters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `priority_level_id` (`priority_level_id`);

--
-- Indices de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indices de la tabla `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indices de la tabla `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_activity` (`user_id`,`activity_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `activity_id` (`activity_id`),
  ADD KEY `priority_level_id` (`priority_level_id`);

--
-- Indices de la tabla `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `priority_level_id` (`priority_level_id`);

--
-- Indices de la tabla `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indices de la tabla `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indices de la tabla `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indices de la tabla `priority_rules`
--
ALTER TABLE `priority_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indices de la tabla `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indices de la tabla `system_recommendations`
--
ALTER TABLE `system_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `academic_semesters`
--
ALTER TABLE `academic_semesters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `activities`
--
ALTER TABLE `activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `priority_rules`
--
ALTER TABLE `priority_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `system_recommendations`
--
ALTER TABLE `system_recommendations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `academic_semesters`
--
ALTER TABLE `academic_semesters`
  ADD CONSTRAINT `academic_semesters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activities_ibfk_2` FOREIGN KEY (`priority_level_id`) REFERENCES `priority_rules` (`id`);

--
-- Filtros para la tabla `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `calendar_events_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `calendar_events_ibfk_3` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `calendar_events_ibfk_4` FOREIGN KEY (`priority_level_id`) REFERENCES `priority_rules` (`id`);

--
-- Filtros para la tabla `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`semester_id`) REFERENCES `academic_semesters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`priority_level_id`) REFERENCES `priority_rules` (`id`);

--
-- Filtros para la tabla `system_recommendations`
--
ALTER TABLE `system_recommendations`
  ADD CONSTRAINT `system_recommendations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `system_recommendations_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;