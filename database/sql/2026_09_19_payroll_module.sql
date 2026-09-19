-- =====================================================================
-- Construmax2 ERP — Módulo de Recursos Humanos y Nómina
-- Script de entrega para phpMyAdmin (MySQL 8)
-- Fecha: 2026-09-19 · Migraciones: 2026_09_19_000001 a 2026_09_19_000015
--
-- Uso: importar este archivo en la base de datos (phpMyAdmin > Importar).
-- Contenido:
--   1. Estructura de las 16 tablas del módulo (DROP + CREATE)
--   2. Alteraciones sobre la tabla `expenses`
--   3. Permisos del módulo (categoría "Nómina")
--   4. Datos iniciales (categoría de gasto "Nómina" y configuración singleton)
--
-- Nota: si las tablas ya existen el script las elimina y las vuelve a
-- crear (sin datos). Para entornos nuevos ejecutar también el resto de
-- migraciones del sistema.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Tablas del módulo
-- ---------------------------------------------------------------------


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `payroll_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `face_recognition_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `face_match_threshold` smallint unsigned NOT NULL DEFAULT '90',
  `kiosk_pin_fallback_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `rekognition_collection_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'construmax-attendance',
  `period_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `period_anchor_date` date DEFAULT NULL,
  `late_tolerance_minutes` smallint unsigned NOT NULL DEFAULT '10',
  `late_discount_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'track_only',
  `overtime_double_multiplier` decimal(4,2) NOT NULL DEFAULT '2.00',
  `overtime_triple_multiplier` decimal(4,2) NOT NULL DEFAULT '3.00',
  `overtime_weekly_threshold_hours` decimal(5,2) NOT NULL DEFAULT '9.00',
  `holiday_worked_extra_multiplier` decimal(4,2) NOT NULL DEFAULT '2.00',
  `vacation_min_days_to_request` decimal(5,2) NOT NULL DEFAULT '1.00',
  `vacation_carryover_months` smallint unsigned NOT NULL DEFAULT '18',
  `incapacity_paid` tinyint(1) NOT NULL DEFAULT '0',
  `incapacity_pay_percentage` smallint unsigned NOT NULL DEFAULT '60',
  `default_daily_hours` decimal(5,2) NOT NULL DEFAULT '8.00',
  `payroll_expense_category_id` bigint unsigned DEFAULT NULL,
  `attendance_capture_retention_months` smallint unsigned NOT NULL DEFAULT '12',
  `remote_geolocation_required` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_settings_payroll_expense_category_id_foreign` (`payroll_expense_category_id`),
  KEY `payroll_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `payroll_settings_payroll_expense_category_id_foreign` FOREIGN KEY (`payroll_expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `employee_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `daily_salary` decimal(12,2) DEFAULT NULL,
  `daily_hours` decimal(5,2) DEFAULT NULL,
  `is_payroll_subject` tinyint(1) NOT NULL DEFAULT '0',
  `is_attendance_subject` tinyint(1) NOT NULL DEFAULT '0',
  `can_remote_attendance` tinyint(1) NOT NULL DEFAULT '0',
  `kiosk_pin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_profiles_user_id_unique` (`user_id`),
  UNIQUE KEY `payroll_profiles_employee_number_unique` (`employee_number`),
  CONSTRAINT `payroll_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `registered_by` bigint unsigned DEFAULT NULL,
  `registered_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `revoked_by` bigint unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_devices_token_hash_unique` (`token_hash`),
  KEY `attendance_devices_registered_by_foreign` (`registered_by`),
  KEY `attendance_devices_revoked_by_foreign` (`revoked_by`),
  CONSTRAINT `attendance_devices_registered_by_foreign` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_devices_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `attendance_device_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `punched_at` datetime NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kiosk',
  `identifier_method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pin',
  `face_similarity` decimal(5,2) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `location_accuracy` decimal(8,2) DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edited_by` bigint unsigned DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `edit_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_logs_attendance_device_id_foreign` (`attendance_device_id`),
  KEY `attendance_logs_edited_by_foreign` (`edited_by`),
  KEY `attendance_logs_user_id_punched_at_index` (`user_id`,`punched_at`),
  CONSTRAINT `attendance_logs_attendance_device_id_foreign` FOREIGN KEY (`attendance_device_id`) REFERENCES `attendance_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_logs_edited_by_foreign` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_day_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_day_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `late_ignored` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_day_overrides_user_id_date_unique` (`user_id`,`date`),
  KEY `attendance_day_overrides_updated_by_foreign` (`updated_by`),
  CONSTRAINT `attendance_day_overrides_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_day_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `meal_minutes` smallint unsigned NOT NULL DEFAULT '60',
  `is_meal_paid` tinyint(1) NOT NULL DEFAULT '0',
  `days` json NOT NULL,
  `required_daily_hours` decimal(5,2) DEFAULT NULL,
  `late_tolerance_minutes` smallint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shift_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shift_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `shift_id` bigint unsigned DEFAULT NULL,
  `rotation` json DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shift_assignments_shift_id_foreign` (`shift_id`),
  KEY `shift_assignments_user_id_start_date_index` (`user_id`,`start_date`),
  KEY `shift_assignments_department_start_date_index` (`department`,`start_date`),
  CONSTRAINT `shift_assignments_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shift_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint unsigned NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lft',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `apply_extra_pay` tinyint(1) NOT NULL DEFAULT '1',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `holidays_date_unique` (`date`),
  KEY `holidays_year_index` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `days` decimal(6,2) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `vacation_request_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `incidents_vacation_request_id_foreign` (`vacation_request_id`),
  KEY `incidents_created_by_foreign` (`created_by`),
  KEY `incidents_approved_by_foreign` (`approved_by`),
  KEY `incidents_user_id_start_date_index` (`user_id`,`start_date`),
  CONSTRAINT `incidents_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidents_vacation_request_id_foreign` FOREIGN KEY (`vacation_request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` decimal(6,2) NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vacation_requests_requested_by_foreign` (`requested_by`),
  KEY `vacation_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `vacation_requests_user_id_start_date_index` (`user_id`,`start_date`),
  CONSTRAINT `vacation_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vacation_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vacation_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `total_gross` decimal(12,2) DEFAULT NULL,
  `total_deductions` decimal(12,2) DEFAULT NULL,
  `total_net` decimal(12,2) DEFAULT NULL,
  `expense_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_periods_closed_by_foreign` (`closed_by`),
  KEY `payroll_periods_expense_id_foreign` (`expense_id`),
  KEY `payroll_periods_status_start_date_index` (`status`,`start_date`),
  CONSTRAINT `payroll_periods_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_periods_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_period_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `concept` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_adjustments_payroll_period_id_foreign` (`payroll_period_id`),
  KEY `payroll_adjustments_user_id_foreign` (`user_id`),
  KEY `payroll_adjustments_created_by_foreign` (`created_by`),
  CONSTRAINT `payroll_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_adjustments_payroll_period_id_foreign` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_adjustments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payslips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_period_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `employee_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `daily_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `daily_hours` decimal(5,2) DEFAULT NULL,
  `days_worked` decimal(6,2) NOT NULL DEFAULT '0.00',
  `days_paid` decimal(6,2) NOT NULL DEFAULT '0.00',
  `unpaid_days` decimal(6,2) NOT NULL DEFAULT '0.00',
  `late_minutes` int NOT NULL DEFAULT '0',
  `late_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_double_minutes` int NOT NULL DEFAULT '0',
  `overtime_triple_minutes` int NOT NULL DEFAULT '0',
  `overtime_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `holiday_days` decimal(6,2) NOT NULL DEFAULT '0.00',
  `holiday_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `vacation_days` decimal(6,2) NOT NULL DEFAULT '0.00',
  `incapacity_days` decimal(6,2) NOT NULL DEFAULT '0.00',
  `incapacity_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `adjustments_earnings` decimal(12,2) NOT NULL DEFAULT '0.00',
  `adjustments_deductions` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_gross` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_deductions` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_net` decimal(12,2) NOT NULL DEFAULT '0.00',
  `generated_at` timestamp NULL DEFAULT NULL,
  `generated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payslips_payroll_period_id_user_id_unique` (`payroll_period_id`,`user_id`),
  KEY `payslips_user_id_foreign` (`user_id`),
  KEY `payslips_generated_by_foreign` (`generated_by`),
  CONSTRAINT `payslips_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payslips_payroll_period_id_foreign` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payslips_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payslip_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payslip_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payslip_id` bigint unsigned NOT NULL,
  `concept` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(8,2) DEFAULT NULL,
  `unit_rate` decimal(12,2) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attendance',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payslip_lines_payslip_id_foreign` (`payslip_id`),
  CONSTRAINT `payslip_lines_payslip_id_foreign` FOREIGN KEY (`payslip_id`) REFERENCES `payslips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payslip_days`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payslip_days` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payslip_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_in` time DEFAULT NULL,
  `lunch_start` time DEFAULT NULL,
  `lunch_end` time DEFAULT NULL,
  `last_out` time DEFAULT NULL,
  `worked_minutes` int NOT NULL DEFAULT '0',
  `late_minutes` int NOT NULL DEFAULT '0',
  `overtime_minutes` int NOT NULL DEFAULT '0',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payslip_days_payslip_id_foreign` (`payslip_id`),
  CONSTRAINT `payslip_days_payslip_id_foreign` FOREIGN KEY (`payslip_id`) REFERENCES `payslips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `face_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `face_enrollments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `collection_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `face_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_image_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `quality` decimal(8,2) DEFAULT NULL,
  `enrolled_by` bigint unsigned DEFAULT NULL,
  `enrolled_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `face_enrollments_enrolled_by_foreign` (`enrolled_by`),
  KEY `face_enrollments_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `face_enrollments_enrolled_by_foreign` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `face_enrollments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ---------------------------------------------------------------------
-- 2. Alteraciones sobre la tabla `expenses`
--    (periodo de nómina espejo; created_by pasa a nullable porque el
--     cierre automático corre sin usuario autenticado)
-- ---------------------------------------------------------------------

ALTER TABLE `expenses`
    MODIFY `created_by` bigint unsigned NULL;

ALTER TABLE `expenses`
    ADD COLUMN `payroll_period_id` bigint unsigned NULL AFTER `deposit_id`,
    ADD UNIQUE KEY `expenses_payroll_period_id_unique` (`payroll_period_id`),
    ADD CONSTRAINT `expenses_payroll_period_id_foreign` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 3. Permisos del módulo (categoría "Nómina")
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `permissions` (`name`, `guard_name`, `category`, `description`, `created_at`, `updated_at`) VALUES
('payroll.settings.manage', 'web', 'Nómina', 'Configurar el módulo de nómina (periodos, retardos, vacaciones, reconocimiento facial)', NOW(), NOW()),
('payroll.profiles.manage', 'web', 'Nómina', 'Editar los datos de nómina y asistencia de los colaboradores', NOW(), NOW()),
('payroll.remote-attendance.manage', 'web', 'Nómina', 'Activar o desactivar la asistencia remota de colaboradores y técnicos', NOW(), NOW()),
('payroll.devices.manage', 'web', 'Nómina', 'Gestionar los dispositivos autorizados del kiosco de asistencia', NOW(), NOW()),
('payroll.shifts.manage', 'web', 'Nómina', 'Gestionar turnos, horarios y sus asignaciones', NOW(), NOW()),
('payroll.incidents.manage', 'web', 'Nómina', 'Registrar y administrar incidencias de asistencia', NOW(), NOW()),
('payroll.vacations.manage', 'web', 'Nómina', 'Ver y administrar el módulo de vacaciones', NOW(), NOW()),
('payroll.vacations.approve', 'web', 'Nómina', 'Aprobar o rechazar solicitudes de vacaciones', NOW(), NOW()),
('payroll.holidays.manage', 'web', 'Nómina', 'Gestionar el calendario de días festivos', NOW(), NOW()),
('payroll.periods.index', 'web', 'Nómina', 'Ver los periodos de nómina y la pre-nómina', NOW(), NOW()),
('payroll.periods.manage', 'web', 'Nómina', 'Editar registros de asistencia, ajustes e incidencias dentro del periodo', NOW(), NOW()),
('payroll.periods.close', 'web', 'Nómina', 'Cerrar y reabrir periodos de nómina', NOW(), NOW()),
('payroll.payslips.view', 'web', 'Nómina', 'Ver e imprimir recibos de nómina de los colaboradores', NOW(), NOW()),
('payroll.faces.manage', 'web', 'Nómina', 'Registrar y eliminar rostros para el reconocimiento facial', NOW(), NOW());

-- ---------------------------------------------------------------------
-- 4. Datos iniciales
-- ---------------------------------------------------------------------

-- Categoría de gasto para el gasto espejo de cada periodo de nómina
INSERT INTO `expense_categories` (`name`, `is_active`, `is_default`, `created_at`, `updated_at`)
SELECT 'Nómina', 1, 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `expense_categories` WHERE `name` = 'Nómina');

-- Configuración singleton del módulo (los valores por defecto aplican;
-- `PayrollSetting::current()` también la crea automáticamente si falta)
INSERT IGNORE INTO `payroll_settings` (`id`, `created_at`, `updated_at`) VALUES (1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Fin del script
-- =====================================================================
