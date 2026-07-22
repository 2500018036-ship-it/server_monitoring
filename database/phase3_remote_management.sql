USE `server_monitoring`;

ALTER TABLE `activity_logs`
	ADD COLUMN IF NOT EXISTS `server_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`,
	ADD COLUMN IF NOT EXISTS `action_status` ENUM('success', 'failed', 'pending') NOT NULL DEFAULT 'success' AFTER `activity`,
	ADD COLUMN IF NOT EXISTS `details` TEXT NULL AFTER `action_status`,
	ADD KEY IF NOT EXISTS `activity_logs_server_id_index` (`server_id`);

CREATE TABLE IF NOT EXISTS `ssh_config` (
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
	CONSTRAINT `ssh_config_server_id_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `ssh_config_created_by_fk`
		FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `terminal_history` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ssh_config_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED DEFAULT NULL,
	`command` TEXT NOT NULL,
	`status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
	`executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `terminal_history_config_time_index` (`ssh_config_id`, `executed_at`),
	KEY `terminal_history_user_id_index` (`user_id`),
	CONSTRAINT `terminal_history_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `terminal_history_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `terminal_logs` (
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
	CONSTRAINT `terminal_logs_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `terminal_logs_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_history` (
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
	CONSTRAINT `service_history_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `service_history_server_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `service_history_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backup_history` (
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
	CONSTRAINT `backup_history_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `backup_history_server_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `backup_history_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `database_backup_settings` (
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

CREATE TABLE IF NOT EXISTS `file_history` (
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
	CONSTRAINT `file_history_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `file_history_server_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `file_history_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `firewall_history` (
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
	CONSTRAINT `firewall_history_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `firewall_history_server_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `firewall_history_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ssl_history` (
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
	CONSTRAINT `ssl_history_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `ssl_history_server_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `ssl_history_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_history` (
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
	CONSTRAINT `cron_history_config_fk`
		FOREIGN KEY (`ssh_config_id`) REFERENCES `ssh_config` (`id`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `cron_history_server_fk`
		FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT `cron_history_user_fk`
		FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		ON UPDATE CASCADE ON DELETE SET NULL
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
SELECT 1, `id` FROM `permissions` WHERE `permission_name` IN (
	'ssh.manage', 'terminal.use', 'quick_actions.run', 'services.manage', 'files.manage',
	'docker.manage', 'database.manage', 'backup.manage', 'cron.manage', 'firewall.manage',
	'ssl.manage', 'system.manage'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `permission_name` IN (
	'terminal.use', 'quick_actions.run', 'services.manage', 'files.manage',
	'docker.manage', 'database.manage', 'backup.manage', 'cron.manage', 'ssl.manage'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `permission_name` IN (
	'quick_actions.run'
);
