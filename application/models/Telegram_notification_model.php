<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_notification_model extends CI_Model
{
	protected $settings_table = 'telegram_notification_settings';
	protected $state_table = 'telegram_notification_state';

	protected $event_columns = array(
		'server_online' => 'notify_server_online',
		'server_offline' => 'notify_server_offline',
		'status_warning' => 'notify_status_warning',
		'cpu_high' => 'notify_cpu_high',
		'ram_high' => 'notify_ram_high',
		'storage_high' => 'notify_storage_high',
		'website_down' => 'notify_website_down',
		'website_recovered' => 'notify_website_recovered',
		'docker_stopped' => 'notify_docker_stopped',
		'mysql_stopped' => 'notify_mysql_stopped',
		'web_service_stopped' => 'notify_web_service_stopped',
		'backup_success' => 'notify_backup_success',
		'backup_failed' => 'notify_backup_failed',
		'ssh_failure' => 'notify_ssh_failure',
	);

	public function __construct()
	{
		parent::__construct();
		$this->ensure_schema();
	}

	public function ensure_schema()
	{
		if ( ! $this->db->table_exists($this->settings_table))
		{
			$this->db->query("
				CREATE TABLE `telegram_notification_settings` (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		if ( ! $this->db->table_exists($this->state_table))
		{
			$this->db->query("
				CREATE TABLE `telegram_notification_state` (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}
	}

	public function settings()
	{
		$row = $this->db
			->order_by('id', 'ASC')
			->limit(1)
			->get($this->settings_table)
			->row();

		if ($row)
		{
			if ((int) $row->cooldown_minutes < 30 && empty($row->updated_at))
			{
				$this->db
					->where('id', $row->id)
					->update($this->settings_table, array('cooldown_minutes' => 30));
				$row->cooldown_minutes = 30;
			}

			return $row;
		}

		$this->db->insert($this->settings_table, array(
			'enabled' => 1,
			'cooldown_minutes' => 30,
			'created_at' => date('Y-m-d H:i:s'),
		));

		return $this->settings();
	}

	public function update_settings($data)
	{
		$settings = $this->settings();
		$record = array(
			'enabled' => ! empty($data['enabled']) ? 1 : 0,
			'cpu_threshold' => $this->percentage($this->value($data, 'cpu_threshold', 80), 80),
			'ram_threshold' => $this->percentage($this->value($data, 'ram_threshold', 80), 80),
			'storage_threshold' => $this->percentage($this->value($data, 'storage_threshold', 90), 90),
			'cooldown_minutes' => max((int) $this->value($data, 'cooldown_minutes', 30), 1),
			'updated_at' => date('Y-m-d H:i:s'),
		);

		foreach ($this->event_columns as $column)
		{
			$record[$column] = ! empty($data[$column]) ? 1 : 0;
		}

		return $this->db
			->where('id', $settings->id)
			->update($this->settings_table, $record);
	}

	public function is_event_enabled($event_type, $settings = NULL)
	{
		$settings = $settings ?: $this->settings();
		$column = isset($this->event_columns[$event_type]) ? $this->event_columns[$event_type] : NULL;

		return $column && (int) $this->value((array) $settings, 'enabled', 1) === 1 && (int) $this->value((array) $settings, $column, 1) === 1;
	}

	public function event_columns()
	{
		return $this->event_columns;
	}

	public function state($event_key)
	{
		return $this->db
			->where('event_key', $event_key)
			->get($this->state_table)
			->row();
	}

	public function remember_state($event_type, $server_id, $event_key, $value)
	{
		$state = $this->state($event_key);
		$now = date('Y-m-d H:i:s');
		$data = array(
			'event_type' => $event_type,
			'event_key' => $event_key,
			'server_id' => $server_id ? (int) $server_id : NULL,
			'last_value' => (string) $value,
			'last_seen_at' => $now,
			'updated_at' => $now,
		);

		if ($state)
		{
			return $this->db->where('id', $state->id)->update($this->state_table, $data);
		}

		$data['created_at'] = $now;

		return $this->db->insert($this->state_table, $data);
	}

	public function mark_sent($event_key, $status, $error = NULL)
	{
		return $this->db
			->where('event_key', $event_key)
			->update($this->state_table, array(
				'last_sent_at' => date('Y-m-d H:i:s'),
				'last_send_status' => in_array($status, array('success', 'failed'), TRUE) ? $status : 'success',
				'last_error' => $error,
				'updated_at' => date('Y-m-d H:i:s'),
			));
	}

	public function cooldown_ready($state, $settings = NULL)
	{
		if ( ! $state || empty($state->last_sent_at))
		{
			return TRUE;
		}

		$settings = $settings ?: $this->settings();
		$cooldown = max((int) $this->value((array) $settings, 'cooldown_minutes', 30), 1) * 60;

		return (time() - strtotime($state->last_sent_at)) >= $cooldown;
	}

	protected function percentage($value, $default)
	{
		$value = is_numeric($value) ? (float) $value : (float) $default;

		return max(1, min(100, $value));
	}

	protected function value($array, $key, $default = NULL)
	{
		if (is_object($array))
		{
			return isset($array->$key) ? $array->$key : $default;
		}

		return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
	}
}
