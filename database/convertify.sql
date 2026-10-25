CREATE DATABASE IF NOT EXISTS `convertify_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `convertify_db`;

CREATE TABLE IF NOT EXISTS `conversion_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_token` CHAR(64) NOT NULL,
    `operation_type` ENUM('convert', 'compress') NOT NULL,
    `source_format` VARCHAR(12) NOT NULL,
    `target_format` VARCHAR(12) NOT NULL,
    `status` ENUM('processing', 'completed', 'failed') NOT NULL DEFAULT 'processing',
    `original_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `output_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `download_name` VARCHAR(200) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_job_token` (`job_token`),
    KEY `idx_expiration` (`expires_at`),
    KEY `idx_status_expiration` (`status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
