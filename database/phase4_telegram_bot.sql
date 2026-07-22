CREATE TABLE IF NOT EXISTS `telegram_bot_settings` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`bot_token_encrypted` TEXT NULL,
	`bot_id` BIGINT DEFAULT NULL,
	`bot_name` VARCHAR(160) DEFAULT NULL,
	`bot_username` VARCHAR(160) DEFAULT NULL,
	`selected_chat_id` VARCHAR(120) DEFAULT NULL,
	`selected_chat_name` VARCHAR(180) DEFAULT NULL,
	`selected_chat_username` VARCHAR(180) DEFAULT NULL,
	`selected_chat_type` VARCHAR(30) DEFAULT NULL,
	`status` VARCHAR(30) NOT NULL DEFAULT 'disconnected',
	`last_error` TEXT NULL,
	`connected_at` DATETIME DEFAULT NULL,
	`last_chat_sync_at` DATETIME DEFAULT NULL,
	`last_test_at` DATETIME DEFAULT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `telegram_bot_chats` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`chat_id` VARCHAR(120) NOT NULL,
	`chat_name` VARCHAR(180) DEFAULT NULL,
	`chat_username` VARCHAR(180) DEFAULT NULL,
	`chat_type` VARCHAR(30) DEFAULT NULL,
	`last_message_at` DATETIME DEFAULT NULL,
	`raw_payload` MEDIUMTEXT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `telegram_bot_chats_chat_id_unique` (`chat_id`),
	KEY `telegram_bot_chats_updated_at_index` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `telegram_notification_settings` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`enabled` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_server_online` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_server_offline` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_status_warning` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_cpu_high` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_ram_high` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_storage_high` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_website_down` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_website_recovered` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_docker_stopped` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_mysql_stopped` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_web_service_stopped` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_backup_success` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_backup_failed` TINYINT(1) NOT NULL DEFAULT 1,
	`notify_ssh_failure` TINYINT(1) NOT NULL DEFAULT 1,
	`cpu_threshold` DECIMAL(5,2) NOT NULL DEFAULT 80.00,
	`ram_threshold` DECIMAL(5,2) NOT NULL DEFAULT 80.00,
	`storage_threshold` DECIMAL(5,2) NOT NULL DEFAULT 90.00,
	`cooldown_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `telegram_notification_state` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`event_type` VARCHAR(80) NOT NULL,
	`event_key` VARCHAR(190) NOT NULL,
	`server_id` INT UNSIGNED DEFAULT NULL,
	`last_value` VARCHAR(120) DEFAULT NULL,
	`last_sent_at` DATETIME DEFAULT NULL,
	`last_send_status` VARCHAR(20) DEFAULT NULL,
	`last_error` TEXT NULL,
	`last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `telegram_notification_state_event_key_unique` (`event_key`),
	KEY `telegram_notification_state_server_index` (`server_id`),
	KEY `telegram_notification_state_type_index` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
