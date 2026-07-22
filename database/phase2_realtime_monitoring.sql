USE `server_monitoring`;

ALTER TABLE `settings`
	ADD COLUMN `agent_api_key` VARCHAR(128) DEFAULT 'sm_agent_00c3ed8d3079d40bcc7395a79e864c50842932b5b39a8b7f' AFTER `monitoring_interval`,
	ADD COLUMN `api_allowed_origins` TEXT NULL AFTER `agent_api_key`,
	ADD COLUMN `api_rate_limit_per_minute` INT UNSIGNED NOT NULL DEFAULT 1000 AFTER `api_allowed_origins`;

UPDATE `settings`
SET `agent_api_key` = COALESCE(NULLIF(`agent_api_key`, ''), 'sm_agent_00c3ed8d3079d40bcc7395a79e864c50842932b5b39a8b7f'),
	`api_rate_limit_per_minute` = COALESCE(NULLIF(`api_rate_limit_per_minute`, 0), 1000)
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

CREATE TABLE IF NOT EXISTS `server_metrics` (
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
	CONSTRAINT `server_metrics_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cpu_history` (
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
	CONSTRAINT `cpu_history_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `memory_history` (
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
	CONSTRAINT `memory_history_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `storage_history` (
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
	CONSTRAINT `storage_history_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `network_history` (
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
	CONSTRAINT `network_history_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `process_history` (
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
	CONSTRAINT `process_history_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_logs` (
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
	CONSTRAINT `service_logs_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `heartbeat` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`server_id` INT UNSIGNED NOT NULL,
	`heartbeat_at` DATETIME NOT NULL,
	`response_time_ms` INT UNSIGNED DEFAULT NULL,
	`latency_ms` INT UNSIGNED DEFAULT NULL,
	`ip_address` VARCHAR(45) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `heartbeat_server_time_index` (`server_id`, `heartbeat_at`),
	CONSTRAINT `heartbeat_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_logs` (
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
	CONSTRAINT `system_logs_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `website_logs` (
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
	CONSTRAINT `website_logs_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `docker_logs` (
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
	CONSTRAINT `docker_logs_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `database_logs` (
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
	CONSTRAINT `database_logs_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_rate_limits` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`rate_key` CHAR(64) NOT NULL,
	`window_start` DATETIME NOT NULL,
	`request_count` INT UNSIGNED NOT NULL DEFAULT 1,
	PRIMARY KEY (`id`),
	UNIQUE KEY `api_rate_limits_key_window_unique` (`rate_key`, `window_start`),
	KEY `api_rate_limits_window_index` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
