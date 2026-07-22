CREATE DATABASE IF NOT EXISTS `server_monitoring`
	CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;

USE `server_monitoring`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `api_rate_limits`;
DROP TABLE IF EXISTS `database_backup_settings`;
DROP TABLE IF EXISTS `backup_history`;
DROP TABLE IF EXISTS `cron_history`;
DROP TABLE IF EXISTS `database_logs`;
DROP TABLE IF EXISTS `docker_logs`;
DROP TABLE IF EXISTS `file_history`;
DROP TABLE IF EXISTS `firewall_history`;
DROP TABLE IF EXISTS `heartbeat`;
DROP TABLE IF EXISTS `memory_history`;
DROP TABLE IF EXISTS `network_history`;
DROP TABLE IF EXISTS `process_history`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `server_metrics`;
DROP TABLE IF EXISTS `service_history`;
DROP TABLE IF EXISTS `service_logs`;
DROP TABLE IF EXISTS `ssl_history`;
DROP TABLE IF EXISTS `ssh_config`;
DROP TABLE IF EXISTS `storage_history`;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `terminal_history`;
DROP TABLE IF EXISTS `terminal_logs`;
DROP TABLE IF EXISTS `website_logs`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `servers`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `roles` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`role_name` VARCHAR(50) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `roles_role_name_unique` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`permission_name` VARCHAR(100) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `permissions_permission_name_unique` (`permission_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`role_id` INT UNSIGNED NOT NULL,
	`permission_id` INT UNSIGNED NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `role_permissions_unique` (`role_id`, `permission_id`),
	KEY `role_permissions_role_id_index` (`role_id`),
	KEY `role_permissions_permission_id_index` (`permission_id`),
	CONSTRAINT `role_permissions_role_id_fk`
		FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `role_permissions_permission_id_fk`
		FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`fullname` VARCHAR(120) NOT NULL,
	`username` VARCHAR(60) NOT NULL,
	`email` VARCHAR(120) NOT NULL,
	`password` VARCHAR(255) NOT NULL,
	`role_id` INT UNSIGNED NOT NULL,
	`photo` VARCHAR(255) DEFAULT NULL,
	`status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
	`remember_token` VARCHAR(255) DEFAULT NULL,
	`last_login` DATETIME DEFAULT NULL,
	`last_login_ip` VARCHAR(45) DEFAULT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `users_username_unique` (`username`),
	UNIQUE KEY `users_email_unique` (`email`),
	KEY `users_role_id_index` (`role_id`),
	KEY `users_status_index` (`status`),
	KEY `users_remember_token_index` (`remember_token`),
	CONSTRAINT `users_role_id_fk`
		FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
		ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servers` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_name` VARCHAR(120) NOT NULL,
	`hostname` VARCHAR(120) DEFAULT NULL,
	`public_ip` VARCHAR(45) DEFAULT NULL,
	`private_ip` VARCHAR(45) DEFAULT NULL,
	`provider` VARCHAR(100) DEFAULT NULL,
	`operating_system` VARCHAR(120) DEFAULT NULL,
	`status` ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `servers_status_index` (`status`),
	KEY `servers_hostname_index` (`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`app_name` VARCHAR(120) NOT NULL DEFAULT 'Server Monitoring',
	`logo` VARCHAR(255) DEFAULT NULL,
	`favicon` VARCHAR(255) DEFAULT NULL,
	`timezone` VARCHAR(80) NOT NULL DEFAULT 'Asia/Jakarta',
	`telegram_bot_token` TEXT NULL,
	`telegram_chat_id` VARCHAR(120) DEFAULT NULL,
	`openai_api_key` TEXT NULL,
	`gemini_api_key` TEXT NULL,
	`ollama_url` VARCHAR(255) DEFAULT NULL,
	`smtp_host` VARCHAR(120) DEFAULT NULL,
	`smtp_port` INT UNSIGNED DEFAULT NULL,
	`smtp_user` VARCHAR(120) DEFAULT NULL,
	`smtp_password` TEXT NULL,
	`monitoring_interval` INT UNSIGNED NOT NULL DEFAULT 10,
	`process_retention_days` INT UNSIGNED NOT NULL DEFAULT 7,
	`log_retention_days` INT UNSIGNED NOT NULL DEFAULT 14,
	`agent_api_key` VARCHAR(128) DEFAULT NULL,
	`api_allowed_origins` TEXT NULL,
	`api_rate_limit_per_minute` INT UNSIGNED NOT NULL DEFAULT 1000,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`activity` VARCHAR(255) NOT NULL,
	`ip_address` VARCHAR(45) DEFAULT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `activity_logs_user_id_index` (`user_id`),
	KEY `activity_logs_created_at_index` (`created_at`),
	CONSTRAINT `activity_logs_user_id_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
	`id` VARCHAR(128) NOT NULL,
	`ip_address` VARCHAR(45) NOT NULL,
	`timestamp` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`data` BLOB NOT NULL,
	PRIMARY KEY (`id`),
	KEY `sessions_timestamp_index` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `role_name`) VALUES
	(1, 'Super Admin'),
	(2, 'Admin'),
	(3, 'Operator'),
	(4, 'Viewer');

INSERT INTO `permissions` (`id`, `permission_name`) VALUES
	(1, 'dashboard.view'),
	(2, 'servers.view'),
	(3, 'monitoring.view'),
	(4, 'website.view'),
	(5, 'docker.view'),
	(6, 'database.view'),
	(7, 'logs.view'),
	(8, 'telegram.manage'),
	(9, 'ai.view'),
	(10, 'users.manage'),
	(11, 'settings.manage'),
	(12, 'profile.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
	(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10), (1, 11), (1, 12),
	(2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 10), (2, 11), (2, 12),
	(3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6), (3, 9), (3, 12),
	(4, 1), (4, 2), (4, 3), (4, 4), (4, 12);

INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `password`, `role_id`, `photo`, `status`, `remember_token`, `last_login`, `last_login_ip`, `created_at`, `updated_at`) VALUES
	(1, 'Super Admin', 'admin', 'admin@servermonitoring.local', '$2y$10$Lo66HWVwmuf4zdYG7myMuet0WQDu47O5xyXdf4PcbgvsRfRrVC5JK', 1, NULL, 'active', NULL, NULL, NULL, CURRENT_TIMESTAMP, NULL);

INSERT INTO `settings` (`id`, `app_name`, `logo`, `favicon`, `timezone`, `telegram_bot_token`, `telegram_chat_id`, `openai_api_key`, `gemini_api_key`, `ollama_url`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_password`, `monitoring_interval`, `process_retention_days`, `log_retention_days`, `agent_api_key`, `api_allowed_origins`, `api_rate_limit_per_minute`, `created_at`) VALUES
	(1, 'Server Monitoring', NULL, NULL, 'Asia/Jakarta', NULL, NULL, NULL, NULL, 'http://localhost:11434', NULL, NULL, NULL, NULL, 10, 7, 14, NULL, NULL, 1000, CURRENT_TIMESTAMP);

UPDATE `settings`
SET `monitoring_interval` = 10,
	`process_retention_days` = 7,
	`log_retention_days` = 14,
	`agent_api_key` = LOWER(CONCAT('sm_install_', SUBSTRING(SHA2(UUID(), 256), 1, 48))),
	`api_rate_limit_per_minute` = 1000
WHERE `id` = 1;

ALTER TABLE `servers`
	ADD COLUMN `agent_id` VARCHAR(100) DEFAULT NULL AFTER `id`,
	ADD COLUMN `api_key` VARCHAR(128) DEFAULT NULL AFTER `agent_id`,
	ADD COLUMN `kernel` VARCHAR(120) DEFAULT NULL AFTER `operating_system`,
	ADD COLUMN `architecture` VARCHAR(60) DEFAULT NULL AFTER `kernel`,
	ADD COLUMN `uptime_seconds` BIGINT UNSIGNED DEFAULT 0 AFTER `architecture`,
	ADD COLUMN `current_time` DATETIME DEFAULT NULL AFTER `uptime_seconds`,
	ADD COLUMN `timezone` VARCHAR(80) DEFAULT NULL AFTER `current_time`,
	ADD COLUMN `azure_region` VARCHAR(120) DEFAULT NULL AFTER `timezone`,
	ADD COLUMN `monitoring_status` ENUM('online', 'offline') NOT NULL DEFAULT 'offline' AFTER `status`,
	ADD COLUMN `last_heartbeat_at` DATETIME DEFAULT NULL AFTER `monitoring_status`,
	ADD COLUMN `last_latency_ms` INT UNSIGNED DEFAULT NULL AFTER `last_heartbeat_at`,
	ADD COLUMN `last_response_time_ms` INT UNSIGNED DEFAULT NULL AFTER `last_latency_ms`,
	ADD COLUMN `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
	ADD UNIQUE KEY `servers_agent_id_unique` (`agent_id`),
	ADD KEY `servers_monitoring_status_index` (`monitoring_status`),
	ADD KEY `servers_last_heartbeat_index` (`last_heartbeat_at`);

CREATE TABLE `server_metrics` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`metric_time` DATETIME NOT NULL,
	`cpu_usage` DECIMAL(5,2) DEFAULT NULL,
	`memory_usage` DECIMAL(5,2) DEFAULT NULL,
	`disk_usage` DECIMAL(5,2) DEFAULT NULL,
	`upload_speed` BIGINT UNSIGNED DEFAULT 0,
	`download_speed` BIGINT UNSIGNED DEFAULT 0,
	`active_connections` INT UNSIGNED DEFAULT 0,
	`payload` LONGTEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `server_metrics_server_time_index` (`server_id`, `metric_time`),
	CONSTRAINT `server_metrics_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cpu_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`metric_time` DATETIME NOT NULL,
	`usage_percent` DECIMAL(5,2) DEFAULT NULL,
	`cores` INT UNSIGNED DEFAULT NULL,
	`model` VARCHAR(255) DEFAULT NULL,
	`frequency_mhz` DECIMAL(10,2) DEFAULT NULL,
	`load_1` DECIMAL(8,2) DEFAULT NULL,
	`load_5` DECIMAL(8,2) DEFAULT NULL,
	`load_15` DECIMAL(8,2) DEFAULT NULL,
	`top_process` VARCHAR(255) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `cpu_history_server_time_index` (`server_id`, `metric_time`),
	CONSTRAINT `cpu_history_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memory_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`metric_time` DATETIME NOT NULL,
	`total_mb` BIGINT UNSIGNED DEFAULT NULL,
	`used_mb` BIGINT UNSIGNED DEFAULT NULL,
	`free_mb` BIGINT UNSIGNED DEFAULT NULL,
	`cache_mb` BIGINT UNSIGNED DEFAULT NULL,
	`buffer_mb` BIGINT UNSIGNED DEFAULT NULL,
	`swap_used_mb` BIGINT UNSIGNED DEFAULT NULL,
	`swap_free_mb` BIGINT UNSIGNED DEFAULT NULL,
	`usage_percent` DECIMAL(5,2) DEFAULT NULL,
	`top_process` VARCHAR(255) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `memory_history_server_time_index` (`server_id`, `metric_time`),
	CONSTRAINT `memory_history_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `storage_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`metric_time` DATETIME NOT NULL,
	`mount_point` VARCHAR(255) DEFAULT '/',
	`disk_total_gb` DECIMAL(12,2) DEFAULT NULL,
	`disk_used_gb` DECIMAL(12,2) DEFAULT NULL,
	`disk_free_gb` DECIMAL(12,2) DEFAULT NULL,
	`disk_percentage` DECIMAL(5,2) DEFAULT NULL,
	`disk_read_speed` BIGINT UNSIGNED DEFAULT 0,
	`disk_write_speed` BIGINT UNSIGNED DEFAULT 0,
	`iops` DECIMAL(12,2) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `storage_history_server_time_index` (`server_id`, `metric_time`),
	CONSTRAINT `storage_history_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `network_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`metric_time` DATETIME NOT NULL,
	`interface_name` VARCHAR(80) DEFAULT NULL,
	`upload_speed` BIGINT UNSIGNED DEFAULT 0,
	`download_speed` BIGINT UNSIGNED DEFAULT 0,
	`total_upload` BIGINT UNSIGNED DEFAULT 0,
	`total_download` BIGINT UNSIGNED DEFAULT 0,
	`packet_sent` BIGINT UNSIGNED DEFAULT 0,
	`packet_received` BIGINT UNSIGNED DEFAULT 0,
	`packet_loss` DECIMAL(5,2) DEFAULT NULL,
	`active_connections` INT UNSIGNED DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `network_history_server_time_index` (`server_id`, `metric_time`),
	CONSTRAINT `network_history_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `process_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`metric_time` DATETIME NOT NULL,
	`pid` INT UNSIGNED DEFAULT NULL,
	`user` VARCHAR(80) DEFAULT NULL,
	`command` VARCHAR(500) DEFAULT NULL,
	`cpu_percent` DECIMAL(5,2) DEFAULT NULL,
	`ram_percent` DECIMAL(5,2) DEFAULT NULL,
	`running_time` VARCHAR(80) DEFAULT NULL,
	`process_type` ENUM('cpu', 'memory') NOT NULL DEFAULT 'cpu',
	PRIMARY KEY (`id`),
	KEY `process_history_server_time_index` (`server_id`, `metric_time`),
	KEY `process_history_type_index` (`process_type`),
	CONSTRAINT `process_history_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`service_name` VARCHAR(120) NOT NULL,
	`status` ENUM('running', 'stopped', 'unknown') NOT NULL DEFAULT 'unknown',
	`action` VARCHAR(80) DEFAULT NULL,
	`log_excerpt` TEXT NULL,
	`logged_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `service_logs_server_time_index` (`server_id`, `logged_at`),
	KEY `service_logs_service_index` (`service_name`, `status`),
	CONSTRAINT `service_logs_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `heartbeat` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`heartbeat_at` DATETIME NOT NULL,
	`response_time_ms` INT UNSIGNED DEFAULT NULL,
	`latency_ms` INT UNSIGNED DEFAULT NULL,
	`ip_address` VARCHAR(45) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `heartbeat_server_time_index` (`server_id`, `heartbeat_at`),
	CONSTRAINT `heartbeat_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`log_type` VARCHAR(80) NOT NULL,
	`level` ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
	`source` VARCHAR(255) DEFAULT NULL,
	`message` TEXT NOT NULL,
	`logged_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `system_logs_server_time_index` (`server_id`, `logged_at`),
	KEY `system_logs_level_index` (`level`),
	KEY `system_logs_type_index` (`log_type`),
	CONSTRAINT `system_logs_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `website_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`domain` VARCHAR(255) NOT NULL,
	`status` ENUM('online', 'offline', 'unknown') NOT NULL DEFAULT 'unknown',
	`http_status` INT UNSIGNED DEFAULT NULL,
	`response_time_ms` INT UNSIGNED DEFAULT NULL,
	`ssl_expired_at` DATETIME DEFAULT NULL,
	`ping_ms` DECIMAL(10,2) DEFAULT NULL,
	`dns_resolve_ms` DECIMAL(10,2) DEFAULT NULL,
	`last_check` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `website_logs_server_time_index` (`server_id`, `last_check`),
	KEY `website_logs_domain_index` (`domain`),
	CONSTRAINT `website_logs_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `docker_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`container_id` VARCHAR(120) DEFAULT NULL,
	`container_name` VARCHAR(255) DEFAULT NULL,
	`image` VARCHAR(255) DEFAULT NULL,
	`status` VARCHAR(120) DEFAULT NULL,
	`cpu_percent` DECIMAL(5,2) DEFAULT NULL,
	`ram_usage` VARCHAR(80) DEFAULT NULL,
	`restart_count` INT UNSIGNED DEFAULT NULL,
	`ports` TEXT NULL,
	`network` TEXT NULL,
	`volume` TEXT NULL,
	`logged_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `docker_logs_server_time_index` (`server_id`, `logged_at`),
	KEY `docker_logs_container_index` (`container_name`),
	CONSTRAINT `docker_logs_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `database_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`engine` VARCHAR(80) DEFAULT 'mysql',
	`status` ENUM('online', 'offline', 'unknown') NOT NULL DEFAULT 'unknown',
	`connection_status` VARCHAR(120) DEFAULT NULL,
	`database_size_mb` DECIMAL(14,2) DEFAULT NULL,
	`slow_queries` INT UNSIGNED DEFAULT NULL,
	`running_queries` INT UNSIGNED DEFAULT NULL,
	`threads` INT UNSIGNED DEFAULT NULL,
	`uptime_seconds` BIGINT UNSIGNED DEFAULT NULL,
	`last_backup` DATETIME DEFAULT NULL,
	`logged_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `database_logs_server_time_index` (`server_id`, `logged_at`),
	CONSTRAINT `database_logs_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_rate_limits` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`rate_key` CHAR(64) NOT NULL,
	`window_start` DATETIME NOT NULL,
	`request_count` INT UNSIGNED NOT NULL DEFAULT 1,
	PRIMARY KEY (`id`),
	UNIQUE KEY `api_rate_limits_key_window_unique` (`rate_key`, `window_start`),
	KEY `api_rate_limits_window_index` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `activity_logs`
	ADD COLUMN `server_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`,
	ADD COLUMN `action_status` ENUM('success', 'failed', 'pending') NOT NULL DEFAULT 'success' AFTER `activity`,
	ADD COLUMN `details` TEXT NULL AFTER `action_status`,
	ADD KEY `activity_logs_server_id_index` (`server_id`);

CREATE TABLE `ssh_config` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`name` VARCHAR(120) NOT NULL,
	`host` VARCHAR(255) NOT NULL,
	`port` INT UNSIGNED NOT NULL DEFAULT 22,
	`username` VARCHAR(120) NOT NULL,
	`auth_type` ENUM('password', 'private_key') NOT NULL DEFAULT 'private_key',
	`password_encrypted` TEXT NULL,
	`private_key_encrypted` MEDIUMTEXT NULL,
	`passphrase_encrypted` TEXT NULL,
	`status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
	`last_connected_at` DATETIME DEFAULT NULL,
	`created_by` INT UNSIGNED DEFAULT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `ssh_config_server_id_index` (`server_id`),
	KEY `ssh_config_status_index` (`status`),
	CONSTRAINT `ssh_config_server_id_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `ssh_config_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `terminal_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`command` TEXT NOT NULL,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `terminal_history_config_time_index` (`ssh_config_id`, `executed_at`),
	KEY `terminal_history_user_id_index` (`user_id`),
	CONSTRAINT `terminal_history_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `terminal_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `terminal_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`command` TEXT NOT NULL,
	`output` LONGTEXT NULL,
	`exit_status` INT DEFAULT NULL,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`started_at` DATETIME NOT NULL,
	`finished_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `terminal_logs_config_time_index` (`ssh_config_id`, `started_at`),
	CONSTRAINT `terminal_logs_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `terminal_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`service_name` VARCHAR(120) NOT NULL,
	`action` VARCHAR(40) NOT NULL,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`output` LONGTEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `service_history_config_time_index` (`ssh_config_id`, `created_at`),
	KEY `service_history_service_index` (`service_name`, `action`),
	CONSTRAINT `service_history_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `service_history_server_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `service_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `backup_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`backup_type` ENUM('website', 'database', 'configuration', 'docker_volume') NOT NULL,
	`database_name` VARCHAR(128) DEFAULT NULL,
	`action` ENUM('backup', 'restore', 'download', 'delete') NOT NULL DEFAULT 'backup',
	`remote_path` VARCHAR(500) DEFAULT NULL,
	`file_name` VARCHAR(255) DEFAULT NULL,
	`local_path` VARCHAR(500) DEFAULT NULL,
	`file_size_bytes` BIGINT UNSIGNED DEFAULT NULL,
	`status` ENUM('success', 'failed', 'pending') NOT NULL DEFAULT 'pending',
	`output` LONGTEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`completed_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `backup_history_config_time_index` (`ssh_config_id`, `created_at`),
	CONSTRAINT `backup_history_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `backup_history_server_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `backup_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `database_backup_settings` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`storage_path` VARCHAR(255) NOT NULL DEFAULT 'uploads/backup/database',
	`backup_format` ENUM('sql') NOT NULL DEFAULT 'sql',
	`compression_zip` TINYINT(1) NOT NULL DEFAULT 0,
	`max_backups` INT UNSIGNED NOT NULL DEFAULT 20,
	`auto_delete_old` TINYINT(1) NOT NULL DEFAULT 1,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `file_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`action` VARCHAR(60) NOT NULL,
	`source_path` VARCHAR(700) DEFAULT NULL,
	`target_path` VARCHAR(700) DEFAULT NULL,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`message` TEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `file_history_config_time_index` (`ssh_config_id`, `created_at`),
	CONSTRAINT `file_history_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `file_history_server_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `file_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `firewall_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`firewall_type` ENUM('ufw', 'iptables') NOT NULL DEFAULT 'ufw',
	`action` VARCHAR(80) NOT NULL,
	`rule` VARCHAR(255) DEFAULT NULL,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`output` LONGTEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `firewall_history_config_time_index` (`ssh_config_id`, `created_at`),
	CONSTRAINT `firewall_history_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `firewall_history_server_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `firewall_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ssl_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`domain` VARCHAR(255) NOT NULL,
	`action` VARCHAR(80) NOT NULL,
	`expired_at` DATETIME DEFAULT NULL,
	`days_remaining` INT DEFAULT NULL,
	`auto_renew_status` VARCHAR(120) DEFAULT NULL,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`output` LONGTEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `ssl_history_config_time_index` (`ssh_config_id`, `created_at`),
	KEY `ssl_history_domain_index` (`domain`),
	CONSTRAINT `ssl_history_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `ssl_history_server_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `ssl_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cron_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`action` VARCHAR(80) NOT NULL,
	`cron_expression` VARCHAR(255) DEFAULT NULL,
	`command` TEXT NULL,
	`enabled` TINYINT(1) NOT NULL DEFAULT 1,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`output` LONGTEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `cron_history_config_time_index` (`ssh_config_id`, `created_at`),
	CONSTRAINT `cron_history_config_fk` FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `cron_history_server_fk` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `cron_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (`permission_name`) VALUES
	('ssh.manage'),
	('terminal.use'),
	('quick_actions.run'),
	('services.manage'),
	('files.manage'),
	('docker.manage'),
	('database.manage'),
	('backup.manage'),
	('cron.manage'),
	('firewall.manage'),
	('ssl.manage'),
	('system.manage');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions` WHERE `permission_name` IN ('ssh.manage', 'terminal.use', 'quick_actions.run', 'services.manage', 'files.manage', 'docker.manage', 'database.manage', 'backup.manage', 'cron.manage', 'firewall.manage', 'ssl.manage', 'system.manage');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `permission_name` IN ('terminal.use', 'quick_actions.run', 'services.manage', 'files.manage', 'docker.manage', 'database.manage', 'backup.manage', 'cron.manage', 'ssl.manage');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `permission_name` IN ('quick_actions.run');
