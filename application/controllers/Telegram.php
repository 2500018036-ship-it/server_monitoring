<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Telegram_bot_model');
		$this->load->model('Telegram_notification_model');
		$this->load->library('Telegram_bot_client');
	}

	public function index()
	{
		$this->render('telegram/index', array(
			'page_title' => 'Telegram Bot',
			'telegram_setting' => $this->Telegram_bot_model->settings(),
			'masked_token' => $this->Telegram_bot_model->masked_token(),
			'chats' => $this->Telegram_bot_model->chats(),
		));
	}

	public function notifications()
	{
		$this->render('telegram/notifications', array(
			'page_title' => 'Notification Settings',
			'notification_setting' => $this->Telegram_notification_model->settings(),
			'notification_events' => $this->notification_events(),
		));
	}

	public function update_notifications()
	{
		$this->require_post();
		$this->form_validation->set_rules('cpu_threshold', 'CPU Threshold', 'required|numeric|greater_than[0]|less_than_equal_to[100]');
		$this->form_validation->set_rules('ram_threshold', 'RAM Threshold', 'required|numeric|greater_than[0]|less_than_equal_to[100]');
		$this->form_validation->set_rules('storage_threshold', 'Storage Threshold', 'required|numeric|greater_than[0]|less_than_equal_to[100]');
		$this->form_validation->set_rules('cooldown_minutes', 'Cooldown', 'required|integer|greater_than[0]');

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('settings/notification-settings');
		}

		$data = array(
			'enabled' => $this->input->post('enabled') ? 1 : 0,
			'cpu_threshold' => $this->input->post('cpu_threshold', TRUE),
			'ram_threshold' => $this->input->post('ram_threshold', TRUE),
			'storage_threshold' => $this->input->post('storage_threshold', TRUE),
			'cooldown_minutes' => $this->input->post('cooldown_minutes', TRUE),
		);

		foreach ($this->Telegram_notification_model->event_columns() as $column)
		{
			$data[$column] = $this->input->post($column) ? 1 : 0;
		}

		$this->Telegram_notification_model->update_settings($data);
		$this->log_telegram_activity('Update Telegram Notification Settings', 'success', 'Notification Settings disimpan.');
		$this->session->set_flashdata('success', 'Notification Settings berhasil disimpan.');

		redirect('settings/notification-settings');
	}

	public function connect()
	{
		$this->require_post();
		$this->form_validation->set_rules('bot_token', 'Bot Token', 'required|trim|min_length[20]');

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('settings/telegram-bot');
		}

		$token = trim((string) $this->input->post('bot_token', TRUE));
		$response = $this->telegram_bot_client->get_me($token);

		if ( ! $response['ok'])
		{
			$this->Telegram_bot_model->mark_failed($response['message']);
			$this->log_telegram_activity('Connect Telegram Bot', 'failed', $response['message']);
			$this->session->set_flashdata('error', 'Bot Token tidak valid: '.html_escape($response['message']));
			redirect('settings/telegram-bot');
		}

		$bot = is_array($response['result']) ? $response['result'] : array();

		if (empty($bot['is_bot']))
		{
			$message = 'Token valid tetapi akun bukan Telegram Bot.';
			$this->Telegram_bot_model->mark_failed($message);
			$this->log_telegram_activity('Connect Telegram Bot', 'failed', $message);
			$this->session->set_flashdata('error', $message);
			redirect('settings/telegram-bot');
		}

		$this->Telegram_bot_model->save_connected_bot($token, $bot);
		$username = isset($bot['username']) ? '@'.$bot['username'] : 'tanpa username';
		$this->log_telegram_activity('Connect Telegram Bot', 'success', 'Bot '.$username.' berhasil terhubung.');
		$this->session->set_flashdata('success', 'Bot berhasil terhubung: '.html_escape(isset($bot['first_name']) ? $bot['first_name'] : $username));

		redirect('settings/telegram-bot');
	}

	public function get_chat_id()
	{
		$this->require_post();
		$token = $this->Telegram_bot_model->token();

		if ( ! $token)
		{
			$message = 'Bot Token belum terhubung atau tidak bisa dibaca.';
			$this->log_telegram_activity('Get Telegram Chat ID', 'failed', $message);
			$this->session->set_flashdata('error', $message);
			redirect('settings/telegram-bot');
		}

		$response = $this->telegram_bot_client->get_updates($token);

		if ( ! $response['ok'])
		{
			$this->Telegram_bot_model->mark_failed($response['message']);
			$this->log_telegram_activity('Get Telegram Chat ID', 'failed', $response['message']);
			$this->session->set_flashdata('error', 'Gagal mengambil Chat ID: '.html_escape($response['message']));
			redirect('settings/telegram-bot');
		}

		$chats = $this->extract_chats($response['result']);
		$saved = $this->Telegram_bot_model->save_chats($chats);
		$this->Telegram_bot_model->touch_chat_sync();

		if ($saved === 0)
		{
			$message = 'Belum ada chat masuk ke bot. Kirim pesan ke bot terlebih dahulu.';
			$this->log_telegram_activity('Get Telegram Chat ID', 'success', $message);
			$this->session->set_flashdata('success', $message);
			redirect('settings/telegram-bot');
		}

		$this->log_telegram_activity('Get Telegram Chat ID', 'success', 'Berhasil mengambil '.$saved.' chat.');
		$this->session->set_flashdata('success', 'Chat ID berhasil diperbarui.');

		redirect('settings/telegram-bot');
	}

	public function select_chat()
	{
		$this->require_post();
		$this->form_validation->set_rules('chat_id', 'Chat ID', 'required|trim');

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('settings/telegram-bot');
		}

		$chat_id = trim((string) $this->input->post('chat_id', TRUE));
		$chat = $this->Telegram_bot_model->find_chat($chat_id);

		if ( ! $chat)
		{
			$message = 'Chat ID tidak ditemukan pada daftar update bot.';
			$this->log_telegram_activity('Pilih Telegram Chat ID', 'failed', $message);
			$this->session->set_flashdata('error', $message);
			redirect('settings/telegram-bot');
		}

		$this->Telegram_bot_model->select_chat($chat_id);
		$this->log_telegram_activity('Pilih Telegram Chat ID', 'success', $chat->chat_name.' ('.$chat->chat_id.')');
		$this->session->set_flashdata('success', 'Chat ID berhasil dipilih.');

		redirect('settings/telegram-bot');
	}

	public function test_message()
	{
		$this->require_post();
		$settings = $this->Telegram_bot_model->settings();
		$token = $this->Telegram_bot_model->token();
		$chat_id = isset($settings->selected_chat_id) ? trim((string) $settings->selected_chat_id) : '';
		$message = trim((string) $this->input->post('message', TRUE));

		if ($message === '')
		{
			$message = 'Test message dari Server Monitoring.';
		}

		if (strlen($message) > 1000)
		{
			$message = substr($message, 0, 1000);
		}

		if ( ! $token || $chat_id === '')
		{
			$error = 'Bot Token dan Chat ID harus terhubung sebelum test message.';
			$this->log_telegram_activity('Test Telegram Message', 'failed', $error);
			$this->session->set_flashdata('error', $error);
			redirect('settings/telegram-bot');
		}

		$response = $this->telegram_bot_client->send_message($token, $chat_id, $message);

		if ( ! $response['ok'])
		{
			$this->log_telegram_activity('Test Telegram Message', 'failed', $response['message']);
			$this->session->set_flashdata('error', 'Test message gagal: '.html_escape($response['message']));
			redirect('settings/telegram-bot');
		}

		$this->Telegram_bot_model->touch_test();
		$this->log_telegram_activity('Test Telegram Message', 'success', 'Pesan test terkirim ke '.$chat_id.'.');
		$this->session->set_flashdata('success', 'Bot berhasil terhubung dan test message berhasil dikirim.');

		redirect('settings/telegram-bot');
	}

	protected function extract_chats($updates)
	{
		$updates = is_array($updates) ? $updates : array();
		$chats = array();

		foreach ($updates as $update)
		{
			$source = $this->chat_source($update);

			if (empty($source['chat']) || empty($source['chat']['id']))
			{
				continue;
			}

			$chat = $source['chat'];
			$chat_id = (string) $chat['id'];
			$timestamp = isset($source['date']) ? (int) $source['date'] : 0;
			$last_message_at = $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : NULL;

			if (isset($chats[$chat_id]) && $timestamp <= (int) $chats[$chat_id]['timestamp'])
			{
				continue;
			}

			$chats[$chat_id] = array(
				'chat_id' => $chat_id,
				'chat_name' => $this->chat_name($chat, isset($source['from']) ? $source['from'] : array()),
				'chat_username' => isset($chat['username']) ? $chat['username'] : (isset($source['from']['username']) ? $source['from']['username'] : NULL),
				'chat_type' => $this->chat_type_label(isset($chat['type']) ? $chat['type'] : ''),
				'last_message_at' => $last_message_at,
				'raw_payload' => json_encode($update),
				'timestamp' => $timestamp,
			);
		}

		foreach ($chats as &$chat)
		{
			unset($chat['timestamp']);
		}

		return array_values($chats);
	}

	protected function notification_events()
	{
		return array(
			'notify_server_online' => array('label' => 'Server Online', 'description' => 'Kirim notifikasi saat server kembali online.'),
			'notify_server_offline' => array('label' => 'Server Offline', 'description' => 'Kirim notifikasi saat server tidak dapat dihubungi.'),
			'notify_status_warning' => array('label' => 'Status Warning', 'description' => 'Kirim notifikasi saat server masuk status warning.'),
			'notify_cpu_high' => array('label' => 'CPU Tinggi', 'description' => 'Kirim notifikasi saat CPU melewati threshold.'),
			'notify_ram_high' => array('label' => 'RAM Tinggi', 'description' => 'Kirim notifikasi saat RAM melewati threshold.'),
			'notify_storage_high' => array('label' => 'Storage Hampir Penuh', 'description' => 'Kirim notifikasi saat storage melewati threshold.'),
			'notify_website_down' => array('label' => 'Website Down', 'description' => 'Kirim notifikasi saat website terdeteksi offline.'),
			'notify_website_recovered' => array('label' => 'Website Kembali Online', 'description' => 'Kirim notifikasi saat website pulih.'),
			'notify_docker_stopped' => array('label' => 'Docker Berhenti', 'description' => 'Kirim notifikasi saat container Docker berhenti.'),
			'notify_mysql_stopped' => array('label' => 'MySQL/MariaDB Berhenti', 'description' => 'Kirim notifikasi saat database service berhenti.'),
			'notify_web_service_stopped' => array('label' => 'Nginx/Apache Berhenti', 'description' => 'Kirim notifikasi saat web server berhenti.'),
			'notify_backup_success' => array('label' => 'Backup Berhasil', 'description' => 'Kirim notifikasi saat backup berhasil.'),
			'notify_backup_failed' => array('label' => 'Backup Gagal', 'description' => 'Kirim notifikasi saat backup gagal.'),
			'notify_ssh_failure' => array('label' => 'Koneksi SSH Gagal', 'description' => 'Kirim notifikasi saat koneksi SSH gagal.'),
		);
	}

	protected function chat_source($update)
	{
		$candidates = array('message', 'edited_message', 'channel_post', 'edited_channel_post', 'my_chat_member', 'chat_member');

		foreach ($candidates as $key)
		{
			if (isset($update[$key]) && is_array($update[$key]))
			{
				return array(
					'chat' => isset($update[$key]['chat']) ? $update[$key]['chat'] : NULL,
					'from' => isset($update[$key]['from']) ? $update[$key]['from'] : array(),
					'date' => isset($update[$key]['date']) ? $update[$key]['date'] : NULL,
				);
			}
		}

		return array();
	}

	protected function chat_name($chat, $from)
	{
		if ( ! empty($chat['title']))
		{
			return $chat['title'];
		}

		$name = trim((isset($chat['first_name']) ? $chat['first_name'] : '').' '.(isset($chat['last_name']) ? $chat['last_name'] : ''));

		if ($name !== '')
		{
			return $name;
		}

		$name = trim((isset($from['first_name']) ? $from['first_name'] : '').' '.(isset($from['last_name']) ? $from['last_name'] : ''));

		if ($name !== '')
		{
			return $name;
		}

		return isset($chat['username']) ? '@'.$chat['username'] : (string) $chat['id'];
	}

	protected function chat_type_label($type)
	{
		$type = strtolower((string) $type);

		if ($type === 'private')
		{
			return 'Private';
		}

		if ($type === 'channel')
		{
			return 'Channel';
		}

		return 'Group';
	}

	protected function log_telegram_activity($activity, $status = 'success', $details = NULL)
	{
		return $this->Activity_model->log(
			$this->current_user['id'],
			$activity,
			$this->input->ip_address(),
			NULL,
			$status,
			$details
		);
	}
}
