<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_bot_model extends CI_Model
{
	protected $settings_table = 'telegram_bot_settings';
	protected $chats_table = 'telegram_bot_chats';

	public function __construct()
	{
		parent::__construct();
		$this->load->library('encryption');
		$this->ensure_schema();
	}

	public function ensure_schema()
	{
		if ( ! $this->db->table_exists($this->settings_table))
		{
			$this->db->query("
				CREATE TABLE `telegram_bot_settings` (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		if ( ! $this->db->table_exists($this->chats_table))
		{
			$this->db->query("
				CREATE TABLE `telegram_bot_chats` (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		$this->import_legacy_settings();
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
			return $row;
		}

		$this->db->insert($this->settings_table, array(
			'status' => 'disconnected',
			'created_at' => date('Y-m-d H:i:s'),
		));

		return $this->settings();
	}

	public function save_connected_bot($token, $bot)
	{
		$settings = $this->settings();
		$now = date('Y-m-d H:i:s');
		$data = array(
			'bot_token_encrypted' => $this->encrypt_value($token),
			'bot_id' => isset($bot['id']) ? (int) $bot['id'] : NULL,
			'bot_name' => isset($bot['first_name']) ? $bot['first_name'] : NULL,
			'bot_username' => isset($bot['username']) ? $bot['username'] : NULL,
			'status' => 'connected',
			'last_error' => NULL,
			'connected_at' => $now,
			'updated_at' => $now,
		);

		$this->db->trans_start();
		$this->db->where('id', $settings->id)->update($this->settings_table, $data);
		$this->clear_legacy_token();
		$this->db->trans_complete();

		return $this->db->trans_status();
	}

	public function mark_failed($message)
	{
		$settings = $this->settings();

		return $this->db
			->where('id', $settings->id)
			->update($this->settings_table, array(
				'status' => 'failed',
				'last_error' => $message,
				'updated_at' => date('Y-m-d H:i:s'),
			));
	}

	public function token()
	{
		$settings = $this->settings();

		return $this->decrypt_value($settings->bot_token_encrypted);
	}

	public function masked_token()
	{
		$token = $this->token();

		if ( ! $token)
		{
			return '-';
		}

		$length = strlen($token);

		if ($length <= 16)
		{
			return substr($token, 0, 4).'...';
		}

		return substr($token, 0, 8).'...'.substr($token, -6);
	}

	public function chats()
	{
		return $this->db
			->order_by('last_message_at', 'DESC')
			->order_by('updated_at', 'DESC')
			->get($this->chats_table)
			->result();
	}

	public function find_chat($chat_id)
	{
		return $this->db
			->where('chat_id', (string) $chat_id)
			->get($this->chats_table)
			->row();
	}

	public function save_chats($chats)
	{
		$count = 0;

		foreach ($chats as $chat)
		{
			if (empty($chat['chat_id']))
			{
				continue;
			}

			$existing = $this->find_chat($chat['chat_id']);
			$data = array(
				'chat_id' => (string) $chat['chat_id'],
				'chat_name' => isset($chat['chat_name']) ? $chat['chat_name'] : NULL,
				'chat_username' => isset($chat['chat_username']) ? $chat['chat_username'] : NULL,
				'chat_type' => isset($chat['chat_type']) ? $chat['chat_type'] : NULL,
				'last_message_at' => isset($chat['last_message_at']) ? $chat['last_message_at'] : NULL,
				'raw_payload' => isset($chat['raw_payload']) ? $chat['raw_payload'] : NULL,
				'updated_at' => date('Y-m-d H:i:s'),
			);

			if ($existing)
			{
				$this->db->where('id', $existing->id)->update($this->chats_table, $data);
			}
			else
			{
				$data['created_at'] = date('Y-m-d H:i:s');
				$this->db->insert($this->chats_table, $data);
			}

			$count++;
		}

		return $count;
	}

	public function select_chat($chat_id)
	{
		$chat = $this->find_chat($chat_id);

		if ( ! $chat)
		{
			return FALSE;
		}

		$settings = $this->settings();

		return $this->db
			->where('id', $settings->id)
			->update($this->settings_table, array(
				'selected_chat_id' => $chat->chat_id,
				'selected_chat_name' => $chat->chat_name,
				'selected_chat_username' => $chat->chat_username,
				'selected_chat_type' => $chat->chat_type,
				'updated_at' => date('Y-m-d H:i:s'),
			));
	}

	public function touch_chat_sync()
	{
		$settings = $this->settings();

		return $this->db
			->where('id', $settings->id)
			->update($this->settings_table, array(
				'last_chat_sync_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			));
	}

	public function touch_test()
	{
		$settings = $this->settings();

		return $this->db
			->where('id', $settings->id)
			->update($this->settings_table, array(
				'last_test_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			));
	}

	protected function encrypt_value($value)
	{
		$value = trim((string) $value);

		return $value === '' ? NULL : $this->encryption->encrypt($value);
	}

	protected function decrypt_value($value)
	{
		if (empty($value))
		{
			return NULL;
		}

		$decrypted = $this->encryption->decrypt($value);

		return $decrypted === FALSE ? NULL : $decrypted;
	}

	protected function import_legacy_settings()
	{
		if ( ! $this->db->table_exists('settings') || ! $this->db->field_exists('telegram_bot_token', 'settings'))
		{
			return;
		}

		$settings = $this->settings_without_import();
		$has_token = $settings && ! empty($settings->bot_token_encrypted);

		if ($has_token)
		{
			return;
		}

		$legacy = $this->db
			->select('id, telegram_bot_token, telegram_chat_id')
			->from('settings')
			->where('telegram_bot_token IS NOT NULL', NULL, FALSE)
			->where('telegram_bot_token !=', '')
			->order_by('id', 'ASC')
			->limit(1)
			->get()
			->row();

		if ( ! $legacy)
		{
			return;
		}

		$row = $this->settings_without_import();
		$data = array(
			'bot_token_encrypted' => $this->encrypt_value($legacy->telegram_bot_token),
			'selected_chat_id' => $legacy->telegram_chat_id,
			'status' => 'disconnected',
			'updated_at' => date('Y-m-d H:i:s'),
		);

		$this->db->where('id', $row->id)->update($this->settings_table, $data);
		$this->clear_legacy_token();
	}

	protected function settings_without_import()
	{
		$row = $this->db
			->order_by('id', 'ASC')
			->limit(1)
			->get($this->settings_table)
			->row();

		if ($row)
		{
			return $row;
		}

		$this->db->insert($this->settings_table, array(
			'status' => 'disconnected',
			'created_at' => date('Y-m-d H:i:s'),
		));

		return $this->db
			->where('id', $this->db->insert_id())
			->get($this->settings_table)
			->row();
	}

	protected function clear_legacy_token()
	{
		if ($this->db->table_exists('settings') && $this->db->field_exists('telegram_bot_token', 'settings'))
		{
			$this->db->update('settings', array('telegram_bot_token' => NULL));
		}
	}
}
