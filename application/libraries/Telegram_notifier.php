<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_notifier
{
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('Telegram_bot_model');
		$this->CI->load->model('Telegram_notification_model');
		$this->CI->load->model('Activity_model');
		$this->CI->load->library('Telegram_bot_client');
	}

	public function monitoring($server_id, $server, $payload, $metric_time = NULL)
	{
		$settings = $this->CI->Telegram_notification_model->settings();
		$metric_time = $metric_time ?: date('Y-m-d H:i:s');
		$server_name = $this->server_name($server, $server_id);
		$status = $this->monitoring_status($server, $payload, $settings);

		$this->server_status($server_id, $server_name, $status, $metric_time, $this->status_reason($status, $payload, $settings));
		$this->resource_threshold($server_id, $server_name, 'cpu_high', 'CPU Tinggi', 'cpu_usage:'.$server_id, $this->payload_float($payload, 'cpu', 'usage_percent'), (float) $settings->cpu_threshold, $metric_time);
		$this->resource_threshold($server_id, $server_name, 'ram_high', 'RAM Tinggi', 'ram_usage:'.$server_id, $this->payload_float($payload, 'memory', 'usage_percent'), (float) $settings->ram_threshold, $metric_time);
		$this->resource_threshold($server_id, $server_name, 'storage_high', 'Storage Hampir Penuh', 'storage_usage:'.$server_id, $this->payload_float($payload, 'storage', 'disk_percentage'), (float) $settings->storage_threshold, $metric_time);
		$this->websites($server_id, $server_name, $this->array_value($payload, 'websites', array()), $metric_time);
		$this->docker($server_id, $server_name, $this->array_value($this->array_value($payload, 'docker', array()), 'containers', array()), $metric_time);
		$this->database($server_id, $server_name, $this->array_value($payload, 'database', array()), $metric_time);
		$this->services($server_id, $server_name, $this->array_value($payload, 'services', array()), $metric_time);
	}

	public function server_offline($server_id, $reason = 'Server offline.', $time = NULL)
	{
		$server = $this->server($server_id);
		$this->server_status($server_id, $this->server_name($server, $server_id), 'offline', $time ?: date('Y-m-d H:i:s'), $reason);
	}

	public function ssh_failure($server_id, $reason = 'SSH gagal terkoneksi.')
	{
		$server = $this->server($server_id);
		$server_name = $this->server_name($server, $server_id);
		$event_key = 'ssh_failure:'.$server_id;
		$state = $this->CI->Telegram_notification_model->state($event_key);
		$this->CI->Telegram_notification_model->remember_state('ssh_failure', $server_id, $event_key, 'failed');

		if ($state && $state->last_value === 'failed' && ! $this->CI->Telegram_notification_model->cooldown_ready($state))
		{
			return FALSE;
		}

		return $this->deliver('ssh_failure', $server_id, $event_key, 'Kegagalan Koneksi SSH', 'Offline', array(
			'Server' => $server_name,
			'Waktu' => date('Y-m-d H:i:s'),
			'Detail' => $reason,
		));
	}

	public function backup($server_id, $success, $message, $file_name = NULL, $event_id = NULL)
	{
		$server = $this->server($server_id);
		$server_name = $this->server_name($server, $server_id);
		$event_type = $success ? 'backup_success' : 'backup_failed';
		$event_key = 'backup:'.$event_type.':'.$server_id.':'.sha1(($event_id ?: uniqid('', TRUE)).'|'.$message);
		$title = $success ? 'Backup Berhasil' : 'Backup Gagal';

		$this->CI->Telegram_notification_model->remember_state($event_type, $server_id, $event_key, $success ? 'success' : 'failed');

		return $this->deliver($event_type, $server_id, $event_key, $title, $success ? 'Success' : 'Failed', array(
			'Server' => $server_name,
			'Waktu' => date('Y-m-d H:i:s'),
			'File' => $file_name ?: '-',
			'Detail' => $message,
		));
	}

	protected function server_status($server_id, $server_name, $new_status, $time, $reason = NULL)
	{
		$state_key = 'server_status_state:'.$server_id;
		$state = $this->CI->Telegram_notification_model->state($state_key);
		$old_status = $state ? $state->last_value : NULL;
		$this->CI->Telegram_notification_model->remember_state('server_status', $server_id, $state_key, $new_status);

		$event_type = NULL;
		$title = NULL;

		if ($new_status === 'online')
		{
			$event_type = 'server_online';
			$title = 'Server Online';
		}
		elseif ($new_status === 'warning')
		{
			$event_type = 'status_warning';
			$title = 'Status Warning';
		}
		elseif ($new_status === 'offline')
		{
			$event_type = 'server_offline';
			$title = 'Monitoring Agent Offline';
		}

		if ( ! $event_type)
		{
			return FALSE;
		}

		if ($new_status === 'online' && $old_status !== 'offline')
		{
			return FALSE;
		}

		if (($new_status === 'warning' || $new_status === 'offline') && $old_status === $new_status)
		{
			return FALSE;
		}

		$event_key = 'server_status:'.$server_id.':'.$new_status;
		$notify_state = $this->CI->Telegram_notification_model->state($event_key);
		$this->CI->Telegram_notification_model->remember_state($event_type, $server_id, $event_key, $new_status);

		if ($notify_state && ! $this->CI->Telegram_notification_model->cooldown_ready($notify_state))
		{
			return FALSE;
		}

		return $this->deliver($event_type, $server_id, $event_key, $title, ucfirst($new_status), array(
			'Server' => $server_name,
			'Waktu' => $time,
			'Status Server' => ucfirst($new_status),
			'Detail' => $reason ?: '-',
		));
	}

	protected function resource_threshold($server_id, $server_name, $event_type, $title, $event_key, $value, $threshold, $time)
	{
		$value = $value === NULL ? 0 : (float) $value;
		$new_value = $value >= $threshold ? 'high' : 'normal';
		$state = $this->CI->Telegram_notification_model->state($event_key);
		$this->CI->Telegram_notification_model->remember_state($event_type, $server_id, $event_key, $new_value);

		if ($new_value !== 'high')
		{
			return FALSE;
		}

		if ($state && $state->last_value === 'high' && ! $this->CI->Telegram_notification_model->cooldown_ready($state))
		{
			return FALSE;
		}

		return $this->deliver($event_type, $server_id, $event_key, $title, 'Warning', array(
			'Server' => $server_name,
			'Waktu' => $time,
			'Pemakaian' => round($value, 2).'%',
			'Threshold' => round($threshold, 2).'%',
		));
	}

	protected function websites($server_id, $server_name, $websites, $time)
	{
		foreach ($websites as $website)
		{
			$domain = trim((string) $this->array_value($website, 'domain', 'unknown'));
			$status = $this->online_status($this->array_value($website, 'status'));
			$event_key = 'website_status:'.$server_id.':'.sha1(strtolower($domain));
			$state = $this->CI->Telegram_notification_model->state($event_key);
			$old_status = $state ? $state->last_value : NULL;
			$this->CI->Telegram_notification_model->remember_state('website_status', $server_id, $event_key, $status);

			if ($status === 'offline' && ($old_status !== 'offline' || $this->CI->Telegram_notification_model->cooldown_ready($state)))
			{
				$this->deliver('website_down', $server_id, $event_key, 'Website Down', 'Offline', array(
					'Server' => $server_name,
					'Waktu' => $time,
					'Website' => $domain,
					'HTTP' => $this->array_value($website, 'http_status', '-'),
					'Response' => $this->array_value($website, 'response_time_ms', '-').' ms',
				));
			}
			elseif ($status === 'online' && $old_status === 'offline')
			{
				$this->deliver('website_recovered', $server_id, $event_key, 'Website Kembali Online', 'Online', array(
					'Server' => $server_name,
					'Waktu' => $time,
					'Website' => $domain,
					'HTTP' => $this->array_value($website, 'http_status', '-'),
					'Response' => $this->array_value($website, 'response_time_ms', '-').' ms',
				));
			}
		}
	}

	protected function docker($server_id, $server_name, $containers, $time)
	{
		foreach ($containers as $container)
		{
			$name = $this->array_value($container, 'container_name', $this->array_value($container, 'name', $this->array_value($container, 'container_id', 'unknown')));
			$status = strtolower((string) $this->array_value($container, 'status', ''));
			$new_value = (strpos($status, 'up') === 0 || strpos($status, 'running') !== FALSE) ? 'running' : 'stopped';
			$event_key = 'docker_status:'.$server_id.':'.sha1(strtolower((string) $name));
			$state = $this->CI->Telegram_notification_model->state($event_key);
			$this->CI->Telegram_notification_model->remember_state('docker_status', $server_id, $event_key, $new_value);

			if ($new_value !== 'stopped')
			{
				continue;
			}

			if ($state && $state->last_value === 'stopped' && ! $this->CI->Telegram_notification_model->cooldown_ready($state))
			{
				continue;
			}

			$this->deliver('docker_stopped', $server_id, $event_key, 'Docker Berhenti', 'Warning', array(
				'Server' => $server_name,
				'Waktu' => $time,
				'Container' => $name,
				'Status' => $status ?: 'stopped',
			));
		}
	}

	protected function database($server_id, $server_name, $database, $time)
	{
		if (empty($database))
		{
			return;
		}

		$status = $this->online_status($this->array_value($database, 'status'));
		$engine = strtolower((string) $this->array_value($database, 'engine', 'mysql'));
		$event_key = 'mysql_status:'.$server_id;
		$state = $this->CI->Telegram_notification_model->state($event_key);
		$this->CI->Telegram_notification_model->remember_state('database_status', $server_id, $event_key, $status);

		if ($status !== 'offline')
		{
			return;
		}

		if ($state && $state->last_value === 'offline' && ! $this->CI->Telegram_notification_model->cooldown_ready($state))
		{
			return;
		}

		$this->deliver('mysql_stopped', $server_id, $event_key, 'MySQL/MariaDB Berhenti', 'Offline', array(
			'Server' => $server_name,
			'Waktu' => $time,
			'Engine' => $engine,
			'Connection' => $this->array_value($database, 'connection_status', '-'),
		));
	}

	protected function services($server_id, $server_name, $services, $time)
	{
		foreach ($services as $service)
		{
			$name = strtolower((string) $this->array_value($service, 'name', 'unknown'));
			$status = strtolower((string) $this->array_value($service, 'status', ''));
			$is_stopped = in_array($status, array('stopped', 'offline', 'failed'), TRUE);
			$event_type = $this->service_event_type($name);

			if ( ! $event_type)
			{
				continue;
			}

			$event_key = $event_type === 'mysql_stopped' ? 'mysql_status:'.$server_id : 'service_status:'.$server_id.':'.$name;
			$state = $this->CI->Telegram_notification_model->state($event_key);
			$this->CI->Telegram_notification_model->remember_state('service_status', $server_id, $event_key, $is_stopped ? 'stopped' : 'running');

			if ( ! $is_stopped)
			{
				continue;
			}

			if ($state && $state->last_value === 'stopped' && ! $this->CI->Telegram_notification_model->cooldown_ready($state))
			{
				continue;
			}

			$this->deliver($event_type, $server_id, $event_key, strtoupper($name).' Berhenti', 'Warning', array(
				'Server' => $server_name,
				'Waktu' => $time,
				'Service' => $name,
				'Status' => $status,
				'Log' => $this->array_value($service, 'log_excerpt', '-'),
			));
		}
	}

	protected function deliver($event_type, $server_id, $event_key, $title, $status, $details)
	{
		$settings = $this->CI->Telegram_notification_model->settings();

		if ( ! $this->CI->Telegram_notification_model->is_event_enabled($event_type, $settings))
		{
			return FALSE;
		}

		$bot = $this->CI->Telegram_bot_model->settings();
		$token = $this->CI->Telegram_bot_model->token();
		$chat_id = isset($bot->selected_chat_id) ? trim((string) $bot->selected_chat_id) : '';
		$message = $this->message($title, $status, $details);

		if ((isset($bot->status) && $bot->status !== 'connected') || ! $token || $chat_id === '')
		{
			$error = 'Telegram Bot belum connected atau Chat ID belum dipilih.';
			$this->log_delivery($server_id, $title, 'failed', $error);
			$this->CI->Telegram_notification_model->mark_sent($event_key, 'failed', $error);
			return FALSE;
		}

		$response = $this->CI->telegram_bot_client->send_message($token, $chat_id, $message);

		if (empty($response['ok']))
		{
			$error = isset($response['message']) ? $response['message'] : 'Telegram API gagal mengirim pesan.';
			$this->log_delivery($server_id, $title, 'failed', $error);
			$this->CI->Telegram_notification_model->mark_sent($event_key, 'failed', $error);
			return FALSE;
		}

		$this->log_delivery($server_id, $title, 'success', $message);
		$this->CI->Telegram_notification_model->mark_sent($event_key, 'success');

		return TRUE;
	}

	protected function message($title, $status, $details)
	{
		$lines = array(
			'Server Monitoring Alert',
			'Alert: '.$title,
			'Status: '.$status,
		);

		foreach ($details as $label => $value)
		{
			$lines[] = $label.': '.trim((string) $value);
		}

		return implode("\n", $lines);
	}

	protected function log_delivery($server_id, $title, $status, $details)
	{
		return $this->CI->Activity_model->log(
			NULL,
			'Telegram notification: '.$title,
			'system',
			$server_id,
			$status,
			$details
		);
	}

	protected function monitoring_status($server, $payload, $settings)
	{
		if ($server && isset($server->monitoring_status) && $server->monitoring_status === 'offline')
		{
			return 'offline';
		}

		if ($this->payload_float($payload, 'cpu', 'usage_percent') >= (float) $settings->cpu_threshold)
		{
			return 'warning';
		}

		if ($this->payload_float($payload, 'memory', 'usage_percent') >= (float) $settings->ram_threshold)
		{
			return 'warning';
		}

		if ($this->payload_float($payload, 'storage', 'disk_percentage') >= (float) $settings->storage_threshold)
		{
			return 'warning';
		}

		foreach ($this->array_value($payload, 'services', array()) as $service)
		{
			$name = strtolower((string) $this->array_value($service, 'name', ''));
			$status = strtolower((string) $this->array_value($service, 'status', ''));

			if ($this->service_event_type($name) && in_array($status, array('stopped', 'offline', 'failed'), TRUE))
			{
				return 'warning';
			}
		}

		return 'online';
	}

	protected function status_reason($status, $payload, $settings)
	{
		if ($status === 'online')
		{
			return 'Seluruh proses monitoring berhasil dijalankan.';
		}

		$reasons = array();
		$cpu = $this->payload_float($payload, 'cpu', 'usage_percent');
		$ram = $this->payload_float($payload, 'memory', 'usage_percent');
		$storage = $this->payload_float($payload, 'storage', 'disk_percentage');

		if ($cpu >= (float) $settings->cpu_threshold)
		{
			$reasons[] = 'CPU '.round($cpu, 2).'%';
		}

		if ($ram >= (float) $settings->ram_threshold)
		{
			$reasons[] = 'RAM '.round($ram, 2).'%';
		}

		if ($storage >= (float) $settings->storage_threshold)
		{
			$reasons[] = 'Storage '.round($storage, 2).'%';
		}

		foreach ($this->array_value($payload, 'services', array()) as $service)
		{
			$name = strtolower((string) $this->array_value($service, 'name', ''));
			$status = strtolower((string) $this->array_value($service, 'status', ''));

			if ($this->service_event_type($name) && in_array($status, array('stopped', 'offline', 'failed'), TRUE))
			{
				$reasons[] = 'Service '.$name.' '.$status;
			}
		}

		return empty($reasons) ? 'Server memerlukan perhatian.' : implode(', ', array_slice($reasons, 0, 4));
	}

	protected function service_event_type($name)
	{
		$name = strtolower((string) $name);

		if (in_array($name, array('mysql', 'mariadb'), TRUE))
		{
			return 'mysql_stopped';
		}

		if (in_array($name, array('nginx', 'apache2', 'httpd'), TRUE))
		{
			return 'web_service_stopped';
		}

		return NULL;
	}

	protected function payload_float($payload, $group, $key)
	{
		$value = $this->array_value($this->array_value($payload, $group, array()), $key);

		return is_numeric($value) ? (float) $value : 0;
	}

	protected function online_status($status)
	{
		$status = strtolower((string) $status);

		return in_array($status, array('online', 'up', 'running', 'healthy'), TRUE) ? 'online' : 'offline';
	}

	protected function server($server_id)
	{
		return $this->CI->db
			->where('id', (int) $server_id)
			->get('servers')
			->row();
	}

	protected function server_name($server, $server_id)
	{
		if ($server && ! empty($server->server_name))
		{
			return $server->server_name;
		}

		if ($server && ! empty($server->hostname))
		{
			return $server->hostname;
		}

		return 'Server #'.(int) $server_id;
	}

	protected function array_value($array, $key, $default = NULL)
	{
		return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
	}
}
