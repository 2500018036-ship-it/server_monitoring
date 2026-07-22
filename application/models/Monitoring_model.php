<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Monitoring_model extends CI_Model
{
	const OFFLINE_AFTER_SECONDS = 180;

	public function mark_stale_servers_offline()
	{
		$threshold = date('Y-m-d H:i:s', time() - self::OFFLINE_AFTER_SECONDS);
		$servers = $this->db
			->select('id, server_name, hostname')
			->from('servers')
			->where('last_heartbeat_at IS NOT NULL', NULL, FALSE)
			->where('last_heartbeat_at <', $threshold)
			->where('monitoring_status', 'online')
			->get()
			->result();

		if (empty($servers))
		{
			return TRUE;
		}

		$ids = array();

		foreach ($servers as $server)
		{
			$ids[] = (int) $server->id;
		}

		$updated = $this->db
			->where_in('id', $ids)
			->update('servers', array('monitoring_status' => 'offline'));

		if ($updated)
		{
			foreach ($servers as $server)
			{
				$this->dispatch_server_offline_notification((int) $server->id, 'Heartbeat tidak diterima dalam '.self::OFFLINE_AFTER_SECONDS.' detik.');
			}
		}

		return $updated;
	}

	public function sync_ssh_config_servers()
	{
		return $this->ensure_ssh_config_servers();
	}

	public function get_servers()
	{
		$this->ensure_ssh_config_servers();
		$this->mark_stale_servers_offline();

		return $this->db
			->select('servers.*')
			->from('servers')
			->order_by('server_name', 'ASC')
			->get()
			->result();
	}

	public function get_server($server_id)
	{
		$this->ensure_ssh_config_servers();
		$this->mark_stale_servers_offline();

		return $this->db
			->where('id', (int) $server_id)
			->get('servers')
			->row();
	}

	public function first_server_id()
	{
		$this->ensure_ssh_config_servers();

		$row = $this->db
			->select('id')
			->from('servers')
			->order_by('server_name', 'ASC')
			->limit(1)
			->get()
			->row();

		return $row ? (int) $row->id : 0;
	}

	public function find_server_by_agent($agent_id)
	{
		if ( ! $agent_id)
		{
			return NULL;
		}

		return $this->db
			->where('agent_id', $agent_id)
			->get('servers')
			->row();
	}

	public function upsert_server_from_payload($payload, $api_key = NULL, $preferred_server_id = NULL)
	{
		$server = $this->array_value($payload, 'server', array());
		$agent_id = $this->array_value($payload, 'agent_id', $this->array_value($server, 'agent_id'));
		$hostname = $this->array_value($server, 'hostname', php_uname('n'));

		if ( ! $agent_id)
		{
			$agent_id = strtolower(preg_replace('/[^a-zA-Z0-9_\-\.]/', '-', $hostname));
		}

		$existing = NULL;
		if ($preferred_server_id)
		{
			$existing = $this->db
				->where('id', (int) $preferred_server_id)
				->get('servers')
				->row();
		}

		if ( ! $existing)
		{
			$existing = $this->find_server_by_agent($agent_id);
		}
		$data = array(
			'agent_id' => $agent_id,
			'api_key' => $api_key,
			'server_name' => $this->array_value($server, 'server_name', $hostname),
			'hostname' => $hostname,
			'public_ip' => $this->array_value($server, 'public_ip'),
			'private_ip' => $this->array_value($server, 'private_ip'),
			'provider' => $this->array_value($server, 'provider', 'Unknown'),
			'operating_system' => $this->array_value($server, 'operating_system'),
			'kernel' => $this->array_value($server, 'kernel'),
			'architecture' => $this->array_value($server, 'architecture'),
			'uptime_seconds' => (int) $this->array_value($server, 'uptime_seconds', 0),
			'current_time' => $this->normalize_datetime($this->array_value($server, 'current_time')),
			'timezone' => $this->array_value($server, 'timezone'),
			'azure_region' => $this->array_value($server, 'azure_region'),
			'status' => 'active',
			'monitoring_status' => 'online',
			'last_heartbeat_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		);

		if ($existing)
		{
			$this->db->where('id', $existing->id)->update('servers', $data);

			return (int) $existing->id;
		}

		$data['created_at'] = date('Y-m-d H:i:s');
		$this->db->insert('servers', $data);

		return (int) $this->db->insert_id();
	}

	public function record_heartbeat($server_id, $response_time_ms = NULL, $latency_ms = NULL, $ip_address = NULL)
	{
		$heartbeat_at = date('Y-m-d H:i:s');

		$this->db->insert('heartbeat', array(
			'server_id' => (int) $server_id,
			'heartbeat_at' => $heartbeat_at,
			'response_time_ms' => $response_time_ms,
			'latency_ms' => $latency_ms,
			'ip_address' => $ip_address,
		));

		return $this->db
			->where('id', (int) $server_id)
			->update('servers', array(
				'monitoring_status' => 'online',
				'last_heartbeat_at' => $heartbeat_at,
				'last_latency_ms' => $latency_ms,
				'last_response_time_ms' => $response_time_ms,
				'updated_at' => $heartbeat_at,
			));
	}

	public function mark_server_offline($server_id, $reason = 'SSH connection failed.', $source = 'status-engine')
	{
		$server_id = (int) $server_id;

		if ( ! $server_id)
		{
			return FALSE;
		}

		$now = date('Y-m-d H:i:s');
		$reason = trim((string) $reason);
		$reason = $reason === '' ? 'SSH connection failed.' : $reason;

		$this->db->trans_start();
		$this->db
			->where('id', $server_id)
			->update('servers', array(
				'monitoring_status' => 'offline',
				'last_latency_ms' => NULL,
				'last_response_time_ms' => NULL,
				'updated_at' => $now,
			));

		$this->insert_status_log($server_id, 'error', $source, $reason, $now);
		$this->db->trans_complete();

		$status = $this->db->trans_status();

		if ($status)
		{
			$this->dispatch_server_offline_notification($server_id, $reason);
		}

		return $status;
	}

	public function record_connection_failure($server_id, $reason = 'SSH connection failed.', $source = 'status-engine')
	{
		$server_id = (int) $server_id;

		if ( ! $server_id)
		{
			return FALSE;
		}

		$now = date('Y-m-d H:i:s');
		$reason = trim((string) $reason);
		$reason = $reason === '' ? 'SSH connection failed.' : $reason;
		$server = $this->db
			->select('monitoring_status, last_heartbeat_at')
			->from('servers')
			->where('id', $server_id)
			->get()
			->row();
		$last_heartbeat = $server && ! empty($server->last_heartbeat_at) ? strtotime($server->last_heartbeat_at) : FALSE;
		$heartbeat_is_stale = ! $last_heartbeat || (time() - $last_heartbeat) >= self::OFFLINE_AFTER_SECONDS;

		if ($heartbeat_is_stale || $this->is_auth_failure($reason))
		{
			return $this->mark_server_offline($server_id, $reason, $source);
		}

		$this->db->trans_start();
		$this->insert_status_log($server_id, 'warning', $source, $reason, $now);
		$this->db
			->where('id', $server_id)
			->update('servers', array('updated_at' => $now));
		$this->db->trans_complete();

		return $this->db->trans_status();
	}

	public function record_metrics($server_id, $payload, $ip_address = NULL)
	{
		$metric_time = $this->normalize_datetime($this->array_value($payload, 'metric_time')) ?: date('Y-m-d H:i:s');
		$cpu = $this->array_value($payload, 'cpu', array());
		$memory = $this->array_value($payload, 'memory', array());
		$storage = $this->array_value($payload, 'storage', array());
		$network = $this->array_value($payload, 'network', array());
		$system = $this->array_value($payload, 'system', array());
		$response_time = $this->to_nullable_int($this->array_value($payload, 'response_time_ms'));
		$latency = $this->to_nullable_int($this->array_value($payload, 'latency_ms'));
		$network = $this->network_with_speeds($server_id, $metric_time, $network);

		if (is_array($payload))
		{
			$payload['network'] = $network;
		}

		$this->db->trans_start();
		$this->record_heartbeat($server_id, $response_time, $latency, $ip_address);
		$this->update_system_snapshot($server_id, $payload, $system);
		$this->insert_server_metric($server_id, $metric_time, $payload, $cpu, $memory, $storage, $network);
		$this->insert_cpu_history($server_id, $metric_time, $cpu);
		$this->insert_memory_history($server_id, $metric_time, $memory);
		$this->insert_storage_history($server_id, $metric_time, $storage);
		$this->insert_network_history($server_id, $metric_time, $network);
		$this->insert_process_history($server_id, $metric_time, $payload);
		$this->insert_service_logs($server_id, $metric_time, $this->array_value($payload, 'services', array()));
		$this->insert_docker_logs($server_id, $metric_time, $this->array_value($payload, 'docker', array()));
		$this->insert_database_logs($server_id, $metric_time, $this->array_value($payload, 'database', array()));
		$this->insert_website_logs($server_id, $metric_time, $this->array_value($payload, 'websites', array()));
		$this->insert_system_logs($server_id, $metric_time, $this->array_value($payload, 'logs', array()));
		$this->db->trans_complete();

		$status = $this->db->trans_status();

		if ($status)
		{
			$this->dispatch_monitoring_notifications($server_id, $payload, $metric_time);
		}

		return $status;
	}

	public function record_logs($server_id, $logs)
	{
		if (empty($logs) || ! is_array($logs))
		{
			return TRUE;
		}

		$this->insert_system_logs($server_id, date('Y-m-d H:i:s'), $logs);

		return TRUE;
	}

	protected function dispatch_monitoring_notifications($server_id, $payload, $metric_time)
	{
		try
		{
			$this->load->library('Telegram_notifier');
			$server = $this->db
				->where('id', (int) $server_id)
				->get('servers')
				->row();
			$this->telegram_notifier->monitoring((int) $server_id, $server, is_array($payload) ? $payload : array(), $metric_time);
		}
		catch (Exception $e)
		{
			log_message('error', 'Telegram monitoring notification failed: '.$e->getMessage());
		}
	}

	protected function dispatch_server_offline_notification($server_id, $reason)
	{
		try
		{
			$this->load->library('Telegram_notifier');
			$this->telegram_notifier->server_offline((int) $server_id, $reason);
		}
		catch (Exception $e)
		{
			log_message('error', 'Telegram offline notification failed: '.$e->getMessage());
		}
	}

	protected function dispatch_ssh_failure_notification($server_id, $reason)
	{
		try
		{
			$this->load->library('Telegram_notifier');
			$this->telegram_notifier->ssh_failure((int) $server_id, $reason);
		}
		catch (Exception $e)
		{
			log_message('error', 'Telegram SSH failure notification failed: '.$e->getMessage());
		}
	}

	public function dashboard_payload($server_id = NULL)
	{
		$this->ensure_ssh_config_servers();
		$this->mark_stale_servers_offline();

		$server_id = $server_id ?: $this->first_server_id();
		$servers = $this->get_servers();
		$server = $server_id ? $this->get_server($server_id) : NULL;
		$latest_metric = $server ? $this->get_latest_metric($server->id) : NULL;
		$payload = $latest_metric && $latest_metric->payload ? json_decode($latest_metric->payload, TRUE) : array();
		$this->decorate_servers($servers);
		$this->decorate_server($server, $latest_metric, is_array($payload) ? $payload : array());

		return array(
			'servers' => $servers,
			'selected_server_id' => $server ? (int) $server->id : 0,
			'server' => $server,
			'metric' => $latest_metric,
			'payload' => is_array($payload) ? $payload : array(),
			'charts' => array(
				'cpu' => $this->history_series('cpu_history', 'usage_percent', $server_id),
				'memory' => $this->history_series('memory_history', 'usage_percent', $server_id),
				'storage' => $this->history_series('storage_history', 'disk_percentage', $server_id),
				'network_upload' => $this->history_series('network_history', 'upload_speed', $server_id),
				'network_download' => $this->history_series('network_history', 'download_speed', $server_id),
			),
			'process_cpu' => $this->get_processes($server_id, 'cpu'),
			'process_memory' => $this->get_processes($server_id, 'memory'),
			'services' => $this->get_services($server_id),
			'docker' => $this->get_docker($server_id),
			'database' => $this->get_database($server_id),
			'websites' => $this->get_websites($server_id),
			'logs' => $this->get_system_logs($server_id, array(), 50),
			'heartbeat' => $this->get_heartbeat($server_id),
			'alerts' => $this->build_alerts($server, $latest_metric, $payload),
		);
	}

	public function decorate_servers(&$servers)
	{
		foreach ($servers as $server)
		{
			$metric = $this->get_latest_metric($server->id);
			$payload = $metric && $metric->payload ? json_decode($metric->payload, TRUE) : array();
			$this->decorate_server($server, $metric, is_array($payload) ? $payload : array());
		}
	}

	public function decorate_server($server, $metric = NULL, $payload = array())
	{
		if ( ! $server)
		{
			return NULL;
		}

		$health = $this->server_health($server, $metric, $payload);
		$server->health_status = $health['status'];
		$server->health_label = isset($health['label']) ? $health['label'] : ucfirst($health['status']);
		$server->health_badge = $health['badge'];
		$server->health_reason = $health['reason'];
		$server->health_summary = $health['summary'];
		$server->last_seen_text = $this->last_seen_text(isset($server->last_heartbeat_at) ? $server->last_heartbeat_at : NULL);

		return $server;
	}

	public function get_latest_metric($server_id)
	{
		if ( ! $server_id)
		{
			return NULL;
		}

		return $this->db
			->where('server_id', (int) $server_id)
			->order_by('metric_time', 'DESC')
			->limit(1)
			->get('server_metrics')
			->row();
	}

	public function history_series($table, $field, $server_id, $limit = 40)
	{
		if ( ! $server_id)
		{
			return array();
		}

		$rows = $this->db
			->select('metric_time, '.$field)
			->from($table)
			->where('server_id', (int) $server_id)
			->order_by('metric_time', 'DESC')
			->limit($limit)
			->get()
			->result_array();

		return array_reverse($rows);
	}

	public function get_processes($server_id, $type = NULL, $search = NULL, $limit = 20)
	{
		if ( ! $server_id)
		{
			return array();
		}

		$latest = $this->latest_time('process_history', 'metric_time', $server_id);

		if ( ! $latest)
		{
			return array();
		}

		$this->db
			->from('process_history')
			->where('server_id', (int) $server_id)
			->where('metric_time', $latest);

		if ($type)
		{
			$this->db->where('process_type', $type);
		}

		if ($search)
		{
			$this->db->group_start()
				->like('command', $search)
				->or_like('user', $search)
				->group_end();
		}

		$order = $type === 'memory' ? 'ram_percent' : 'cpu_percent';

		return $this->db
			->order_by($order, 'DESC')
			->limit($limit)
			->get()
			->result();
	}

	public function get_services($server_id)
	{
		return $this->latest_rows('service_logs', 'logged_at', $server_id, 50);
	}

	public function get_docker($server_id)
	{
		return $this->latest_rows('docker_logs', 'logged_at', $server_id, 100);
	}

	public function get_database($server_id)
	{
		return $this->latest_rows('database_logs', 'logged_at', $server_id, 20);
	}

	public function get_websites($server_id)
	{
		return $this->latest_rows('website_logs', 'last_check', $server_id, 100);
	}

	public function get_system_logs($server_id, $filters = array(), $limit = 200)
	{
		if ( ! $server_id)
		{
			return array();
		}

		$this->db
			->from('system_logs')
			->where('server_id', (int) $server_id);

		if ( ! empty($filters['level']))
		{
			$this->db->where('level', $filters['level']);
		}

		if ( ! empty($filters['log_type']))
		{
			$this->db->where('log_type', $filters['log_type']);
		}

		if ( ! empty($filters['date']))
		{
			$this->db->like('logged_at', $filters['date'], 'after');
		}

		if ( ! empty($filters['search']))
		{
			$this->db->like('message', $filters['search']);
		}

		return $this->db
			->order_by('logged_at', 'DESC')
			->limit($limit)
			->get()
			->result();
	}

	public function get_heartbeat($server_id, $limit = 20)
	{
		if ( ! $server_id)
		{
			return array();
		}

		return $this->db
			->where('server_id', (int) $server_id)
			->order_by('heartbeat_at', 'DESC')
			->limit($limit)
			->get('heartbeat')
			->result();
	}

	public function increment_rate_limit($rate_key, $limit)
	{
		$window = date('Y-m-d H:i:00');
		$row = $this->db
			->where('rate_key', $rate_key)
			->where('window_start', $window)
			->get('api_rate_limits')
			->row();

		if ( ! $row)
		{
			$this->db->insert('api_rate_limits', array(
				'rate_key' => $rate_key,
				'window_start' => $window,
				'request_count' => 1,
			));

			return TRUE;
		}

		if ((int) $row->request_count >= (int) $limit)
		{
			return FALSE;
		}

		return $this->db
			->where('id', $row->id)
			->set('request_count', 'request_count + 1', FALSE)
			->update('api_rate_limits');
	}

	public function build_alerts($server, $metric, $payload)
	{
		$alerts = array();

		if ($server && isset($server->health_status) && $server->health_status === 'offline')
		{
			$alerts[] = array('level' => 'danger', 'message' => $server->health_reason ?: 'Server offline atau monitoring tidak merespons.');
		}
		elseif ($server && isset($server->health_status) && $server->health_status === 'warning')
		{
			$alerts[] = array('level' => 'warning', 'message' => $server->health_reason ?: 'Server memerlukan perhatian.');
		}

		if ($metric)
		{
			if ((float) $metric->cpu_usage > 90)
			{
				$alerts[] = array('level' => 'danger', 'message' => 'CPU usage melewati 90%.');
			}

			if ((float) $metric->memory_usage > 90)
			{
				$alerts[] = array('level' => 'danger', 'message' => 'RAM usage melewati 90%.');
			}

			if ((float) $metric->disk_usage > 90)
			{
				$alerts[] = array('level' => 'danger', 'message' => 'Disk usage melewati 90%.');
			}
		}

		foreach ($this->array_value($payload, 'services', array()) as $service)
		{
			$name = strtolower((string) $this->array_value($service, 'name', ''));
			$status = strtolower((string) $this->array_value($service, 'status', ''));

			if ($this->is_important_service($name) && $this->is_service_alert_status($status))
			{
				$alerts[] = array('level' => 'danger', 'message' => 'Service '.($name ?: 'unknown').' '.$status.'.');
			}
		}

		foreach ($this->array_value($payload, 'websites', array()) as $website)
		{
			if ($this->array_value($website, 'status') === 'offline')
			{
				$alerts[] = array('level' => 'danger', 'message' => 'Website '.$this->array_value($website, 'domain', 'unknown').' offline.');
			}
		}

		return $alerts;
	}

	public function server_health($server, $metric = NULL, $payload = array())
	{
		$last_heartbeat = isset($server->last_heartbeat_at) ? $server->last_heartbeat_at : NULL;
		$heartbeat_age = $last_heartbeat ? time() - strtotime($last_heartbeat) : NULL;

		if ( ! $server || $server->monitoring_status === 'offline' || $last_heartbeat === NULL || ($heartbeat_age !== NULL && $heartbeat_age > self::OFFLINE_AFTER_SECONDS))
		{
			$reason = $last_heartbeat === NULL
				? 'Monitoring agent belum pernah mengirim heartbeat ke aplikasi ini.'
				: 'Monitoring agent tidak mengirim data selama lebih dari '.self::OFFLINE_AFTER_SECONDS.' detik. VPS bisa saja tetap hidup, tetapi jalur realtime agent sedang putus.';

			return array(
				'status' => 'offline',
				'label' => 'Agent Offline',
				'badge' => 'danger',
				'reason' => $reason,
				'summary' => 'Agent Offline'.($last_heartbeat ? ' - Last Seen '.$this->last_seen_text($last_heartbeat) : ''),
			);
		}

		$warnings = array();

		if ($metric)
		{
			if ((float) $metric->cpu_usage > 80)
			{
				$warnings[] = 'CPU '.round((float) $metric->cpu_usage, 2).'%';
			}

			if ((float) $metric->memory_usage > 80)
			{
				$warnings[] = 'RAM '.round((float) $metric->memory_usage, 2).'%';
			}

			if ((float) $metric->disk_usage > 90)
			{
				$warnings[] = 'Disk '.round((float) $metric->disk_usage, 2).'%';
			}
		}

		foreach ($this->array_value($payload, 'services', array()) as $service)
		{
			$name = strtolower((string) $this->array_value($service, 'name', ''));
			$status = strtolower((string) $this->array_value($service, 'status', ''));

			if ($this->is_important_service($name) && $this->is_service_alert_status($status))
			{
				$warnings[] = 'Service '.($name ?: 'unknown').' '.$status;
			}
		}

		if ( ! empty($warnings))
		{
			return array(
				'status' => 'warning',
				'badge' => 'warning',
				'reason' => implode(', ', array_slice($warnings, 0, 3)),
				'summary' => $warnings[0],
			);
		}

		return array(
			'status' => 'online',
			'badge' => 'success',
			'reason' => 'Monitoring aktif, SSH/heartbeat tersedia, dan layanan utama normal.',
			'summary' => $this->short_os(isset($server->operating_system) ? $server->operating_system : '').($server->public_ip ? ' | '.$server->public_ip : ''),
		);
	}

	protected function short_os($value)
	{
		$value = trim((string) $value);

		if ($value === '')
		{
			return 'Server';
		}

		if (preg_match('/Ubuntu[\s\-]+([0-9]+\.[0-9]+)/i', $value, $match))
		{
			return 'Ubuntu '.$match[1];
		}

		if (preg_match('/(Debian|CentOS|Rocky|AlmaLinux|Windows Server)[^\d]*([0-9]+(?:\.[0-9]+)?)/i', $value, $match))
		{
			return $match[1].' '.$match[2];
		}

		return substr($value, 0, 46);
	}

	protected function is_important_service($name)
	{
		$name = strtolower(trim((string) $name));
		$important = array('nginx', 'apache2', 'httpd', 'php-fpm', 'mysql', 'mariadb', 'docker', 'ssh', 'sshd');

		return in_array($name, $important, TRUE);
	}

	protected function is_service_alert_status($status)
	{
		$status = strtolower(trim((string) $status));

		return in_array($status, array('stopped', 'offline', 'failed'), TRUE);
	}

	protected function ensure_ssh_config_servers()
	{
		static $synced = FALSE;

		if ($synced || ! $this->db->table_exists('ssh_config'))
		{
			return TRUE;
		}

		$synced = TRUE;

		$configs = $this->db
			->select('ssh_config.id, ssh_config.server_id, ssh_config.name, ssh_config.host, servers.id AS linked_server_id')
			->from('ssh_config')
			->join('servers', 'servers.id = ssh_config.server_id', 'left')
			->where('ssh_config.status', 'active')
			->get()
			->result();

		foreach ($configs as $config)
		{
			$now = date('Y-m-d H:i:s');
			$agent_id = 'ssh-config-'.$config->id;
			$name = trim((string) $config->name) !== '' ? $config->name : 'SSH Server '.$config->id;
			$server_id = ! empty($config->linked_server_id) ? (int) $config->linked_server_id : 0;

			if ( ! $server_id)
			{
				$existing = $this->db
					->where('agent_id', $agent_id)
					->get('servers')
					->row();
				$server_id = $existing ? (int) $existing->id : 0;
			}

			if ( ! $server_id)
			{
				$this->db->insert('servers', array(
					'agent_id' => $agent_id,
					'api_key' => 'ssh-pull',
					'server_name' => $name,
					'hostname' => $config->host,
					'public_ip' => $config->host,
					'provider' => 'SSH Pull',
					'status' => 'active',
					'monitoring_status' => 'offline',
					'last_heartbeat_at' => NULL,
					'created_at' => $now,
					'updated_at' => $now,
				));

				$server_id = (int) $this->db->insert_id();
			}

			if ($server_id)
			{
				$this->db
					->where('id', $server_id)
					->update('servers', array(
						'server_name' => $name,
						'public_ip' => $config->host,
						'updated_at' => $now,
					));

				$this->db
					->where('id', (int) $config->id)
					->update('ssh_config', array(
						'server_id' => $server_id,
						'updated_at' => $now,
					));
			}
		}

		return TRUE;
	}

	protected function last_seen_text($datetime)
	{
		if ( ! $datetime)
		{
			return '-';
		}

		$seconds = max(time() - strtotime($datetime), 0);

		if ($seconds < 60)
		{
			return $seconds.' detik yang lalu';
		}

		if ($seconds < 3600)
		{
			return floor($seconds / 60).' menit yang lalu';
		}

		if ($seconds < 86400)
		{
			return floor($seconds / 3600).' jam yang lalu';
		}

		return floor($seconds / 86400).' hari yang lalu';
	}

	protected function update_system_snapshot($server_id, $payload, $system)
	{
		$server = $this->array_value($payload, 'server', array());
		$data = array(
			'server_name' => $this->array_value($server, 'server_name'),
			'hostname' => $this->array_value($server, 'hostname', $this->array_value($system, 'hostname')),
			'public_ip' => $this->array_value($server, 'public_ip'),
			'private_ip' => $this->array_value($server, 'private_ip'),
			'provider' => $this->array_value($server, 'provider'),
			'operating_system' => $this->array_value($server, 'operating_system', $this->array_value($system, 'operating_system')),
			'kernel' => $this->array_value($server, 'kernel', $this->array_value($system, 'kernel')),
			'architecture' => $this->array_value($server, 'architecture', $this->array_value($system, 'architecture')),
			'uptime_seconds' => (int) $this->array_value($server, 'uptime_seconds', $this->array_value($system, 'uptime_seconds', 0)),
			'current_time' => $this->normalize_datetime($this->array_value($server, 'current_time')),
			'timezone' => $this->array_value($server, 'timezone'),
			'azure_region' => $this->array_value($server, 'azure_region'),
			'monitoring_status' => 'online',
			'updated_at' => date('Y-m-d H:i:s'),
		);

		$this->db
			->where('id', (int) $server_id)
			->update('servers', array_filter($data, function ($value) {
				return $value !== NULL && $value !== '';
			}));
	}

	protected function insert_server_metric($server_id, $metric_time, $payload, $cpu, $memory, $storage, $network)
	{
		return $this->db->insert('server_metrics', array(
			'server_id' => (int) $server_id,
			'metric_time' => $metric_time,
			'cpu_usage' => $this->to_nullable_float($this->array_value($cpu, 'usage_percent')),
			'memory_usage' => $this->to_nullable_float($this->array_value($memory, 'usage_percent')),
			'disk_usage' => $this->to_nullable_float($this->array_value($storage, 'disk_percentage')),
			'upload_speed' => (int) $this->array_value($network, 'upload_speed', 0),
			'download_speed' => (int) $this->array_value($network, 'download_speed', 0),
			'active_connections' => (int) $this->array_value($network, 'active_connections', 0),
			'payload' => json_encode($payload),
		));
	}

	protected function insert_cpu_history($server_id, $metric_time, $cpu)
	{
		$top = $this->first_process_name($this->array_value($cpu, 'top_processes', array()));

		return $this->db->insert('cpu_history', array(
			'server_id' => (int) $server_id,
			'metric_time' => $metric_time,
			'usage_percent' => $this->to_nullable_float($this->array_value($cpu, 'usage_percent')),
			'cores' => $this->to_nullable_int($this->array_value($cpu, 'cores')),
			'model' => $this->array_value($cpu, 'model'),
			'frequency_mhz' => $this->to_nullable_float($this->array_value($cpu, 'frequency_mhz')),
			'load_1' => $this->to_nullable_float($this->array_value($cpu, 'load_1')),
			'load_5' => $this->to_nullable_float($this->array_value($cpu, 'load_5')),
			'load_15' => $this->to_nullable_float($this->array_value($cpu, 'load_15')),
			'top_process' => $top,
		));
	}

	protected function insert_memory_history($server_id, $metric_time, $memory)
	{
		$top = $this->first_process_name($this->array_value($memory, 'top_processes', array()));

		return $this->db->insert('memory_history', array(
			'server_id' => (int) $server_id,
			'metric_time' => $metric_time,
			'total_mb' => $this->to_nullable_int($this->array_value($memory, 'total_mb')),
			'used_mb' => $this->to_nullable_int($this->array_value($memory, 'used_mb')),
			'free_mb' => $this->to_nullable_int($this->array_value($memory, 'free_mb')),
			'cache_mb' => $this->to_nullable_int($this->array_value($memory, 'cache_mb')),
			'buffer_mb' => $this->to_nullable_int($this->array_value($memory, 'buffer_mb')),
			'swap_used_mb' => $this->to_nullable_int($this->array_value($memory, 'swap_used_mb')),
			'swap_free_mb' => $this->to_nullable_int($this->array_value($memory, 'swap_free_mb')),
			'usage_percent' => $this->to_nullable_float($this->array_value($memory, 'usage_percent')),
			'top_process' => $top,
		));
	}

	protected function insert_storage_history($server_id, $metric_time, $storage)
	{
		$disks = $this->array_value($storage, 'disks', array());

		if (empty($disks))
		{
			$disks = array($storage);
		}

		foreach ($disks as $disk)
		{
			$this->db->insert('storage_history', array(
				'server_id' => (int) $server_id,
				'metric_time' => $metric_time,
				'mount_point' => $this->array_value($disk, 'mount_point', '/'),
				'disk_total_gb' => $this->to_nullable_float($this->array_value($disk, 'disk_total_gb', $this->array_value($storage, 'disk_total_gb'))),
				'disk_used_gb' => $this->to_nullable_float($this->array_value($disk, 'disk_used_gb', $this->array_value($storage, 'disk_used_gb'))),
				'disk_free_gb' => $this->to_nullable_float($this->array_value($disk, 'disk_free_gb', $this->array_value($storage, 'disk_free_gb'))),
				'disk_percentage' => $this->to_nullable_float($this->array_value($disk, 'disk_percentage', $this->array_value($storage, 'disk_percentage'))),
				'disk_read_speed' => (int) $this->array_value($disk, 'disk_read_speed', $this->array_value($storage, 'disk_read_speed', 0)),
				'disk_write_speed' => (int) $this->array_value($disk, 'disk_write_speed', $this->array_value($storage, 'disk_write_speed', 0)),
				'iops' => $this->to_nullable_float($this->array_value($disk, 'iops', $this->array_value($storage, 'iops'))),
			));
		}
	}

	protected function insert_network_history($server_id, $metric_time, $network)
	{
		$interfaces = $this->array_value($network, 'interfaces', array());

		if (empty($interfaces))
		{
			$interfaces = array($network);
		}

		foreach ($interfaces as $interface)
		{
			$upload_speed = (int) $this->array_value($interface, 'upload_speed', $this->array_value($network, 'upload_speed', 0));
			$download_speed = (int) $this->array_value($interface, 'download_speed', $this->array_value($network, 'download_speed', 0));
			$total_upload = (int) $this->array_value($interface, 'total_upload', $this->array_value($network, 'total_upload', 0));
			$total_download = (int) $this->array_value($interface, 'total_download', $this->array_value($network, 'total_download', 0));

			$this->db->insert('network_history', array(
				'server_id' => (int) $server_id,
				'metric_time' => $metric_time,
				'interface_name' => $this->array_value($interface, 'interface_name', $this->array_value($network, 'interface_name')),
				'upload_speed' => $upload_speed,
				'download_speed' => $download_speed,
				'total_upload' => $total_upload,
				'total_download' => $total_download,
				'packet_sent' => (int) $this->array_value($interface, 'packet_sent', $this->array_value($network, 'packet_sent', 0)),
				'packet_received' => (int) $this->array_value($interface, 'packet_received', $this->array_value($network, 'packet_received', 0)),
				'packet_loss' => $this->to_nullable_float($this->array_value($interface, 'packet_loss', $this->array_value($network, 'packet_loss'))),
				'active_connections' => (int) $this->array_value($interface, 'active_connections', $this->array_value($network, 'active_connections', 0)),
			));
		}
	}

	protected function network_with_speeds($server_id, $metric_time, $network)
	{
		if (empty($network) || ! is_array($network))
		{
			return $network;
		}

		$has_interfaces = ! empty($network['interfaces']) && is_array($network['interfaces']);
		$interfaces = $has_interfaces ? $network['interfaces'] : array($network);

		foreach ($interfaces as $index => $interface)
		{
			$name = $this->array_value($interface, 'interface_name', $this->array_value($network, 'interface_name', 'default'));
			$total_upload = (int) $this->array_value($interface, 'total_upload', 0);
			$total_download = (int) $this->array_value($interface, 'total_download', 0);
			$upload_speed = (int) $this->array_value($interface, 'upload_speed', 0);
			$download_speed = (int) $this->array_value($interface, 'download_speed', 0);

			if (($upload_speed <= 0 || $download_speed <= 0) && ($total_upload > 0 || $total_download > 0))
			{
				$previous = $this->latest_network_counter($server_id, $name);
				if ($previous)
				{
					$elapsed = max(strtotime($metric_time) - strtotime($previous->metric_time), 1);
					if ($elapsed > 300)
					{
						$elapsed = 3;
					}

					if ($upload_speed <= 0 && $total_upload >= (int) $previous->total_upload)
					{
						$upload_speed = (int) floor(($total_upload - (int) $previous->total_upload) / $elapsed);
					}

					if ($download_speed <= 0 && $total_download >= (int) $previous->total_download)
					{
						$download_speed = (int) floor(($total_download - (int) $previous->total_download) / $elapsed);
					}
				}
			}

			$interfaces[$index]['upload_speed'] = max($upload_speed, 0);
			$interfaces[$index]['download_speed'] = max($download_speed, 0);
		}

		if ($has_interfaces)
		{
			$network['interfaces'] = $interfaces;
			$first = reset($interfaces);

			if (is_array($first))
			{
				$network['upload_speed'] = (int) $this->array_value($first, 'upload_speed', 0);
				$network['download_speed'] = (int) $this->array_value($first, 'download_speed', 0);
			}

			return $network;
		}

		return reset($interfaces);
	}

	protected function latest_network_counter($server_id, $interface_name)
	{
		return $this->db
			->where('server_id', (int) $server_id)
			->where('interface_name', $interface_name)
			->order_by('metric_time', 'DESC')
			->limit(1)
			->get('network_history')
			->row();
	}

	protected function insert_process_history($server_id, $metric_time, $payload)
	{
		$process = $this->array_value($payload, 'processes', array());
		$groups = array(
			'cpu' => array_slice($this->array_value($process, 'top_cpu', $this->array_value($this->array_value($payload, 'cpu', array()), 'top_processes', array())), 0, 20),
			'memory' => array_slice($this->array_value($process, 'top_memory', $this->array_value($this->array_value($payload, 'memory', array()), 'top_processes', array())), 0, 20),
		);

		foreach ($groups as $type => $items)
		{
			foreach ($items as $item)
			{
				$this->db->insert('process_history', array(
					'server_id' => (int) $server_id,
					'metric_time' => $metric_time,
					'pid' => $this->to_nullable_int($this->array_value($item, 'pid')),
					'user' => $this->array_value($item, 'user'),
					'command' => $this->array_value($item, 'command'),
					'cpu_percent' => $this->to_nullable_float($this->array_value($item, 'cpu')),
					'ram_percent' => $this->to_nullable_float($this->array_value($item, 'ram')),
					'running_time' => $this->array_value($item, 'running_time'),
					'process_type' => $type,
				));
			}
		}
	}

	protected function insert_service_logs($server_id, $metric_time, $services)
	{
		foreach ($services as $service)
		{
			$this->db->insert('service_logs', array(
				'server_id' => (int) $server_id,
				'service_name' => $this->array_value($service, 'name', 'unknown'),
				'status' => $this->normalize_service_status($this->array_value($service, 'status')),
				'action' => $this->array_value($service, 'action'),
				'log_excerpt' => $this->array_value($service, 'log_excerpt'),
				'logged_at' => $metric_time,
			));
		}
	}

	protected function insert_docker_logs($server_id, $metric_time, $docker)
	{
		$containers = $this->array_value($docker, 'containers', array());

		foreach ($containers as $container)
		{
			$this->db->insert('docker_logs', array(
				'server_id' => (int) $server_id,
				'container_id' => $this->array_value($container, 'container_id'),
				'container_name' => $this->array_value($container, 'container_name'),
				'image' => $this->array_value($container, 'image'),
				'status' => $this->array_value($container, 'status'),
				'cpu_percent' => $this->to_nullable_float($this->array_value($container, 'cpu')),
				'ram_usage' => $this->array_value($container, 'ram'),
				'restart_count' => $this->to_nullable_int($this->array_value($container, 'restart_count')),
				'ports' => $this->stringify($this->array_value($container, 'ports')),
				'network' => $this->stringify($this->array_value($container, 'network')),
				'volume' => $this->stringify($this->array_value($container, 'volume')),
				'logged_at' => $metric_time,
			));
		}
	}

	protected function insert_database_logs($server_id, $metric_time, $database)
	{
		if (empty($database))
		{
			return;
		}

		$this->db->insert('database_logs', array(
			'server_id' => (int) $server_id,
			'engine' => $this->array_value($database, 'engine', 'mysql'),
			'status' => $this->normalize_online_status($this->array_value($database, 'status')),
			'connection_status' => $this->array_value($database, 'connection_status'),
			'database_size_mb' => $this->to_nullable_float($this->array_value($database, 'database_size_mb')),
			'slow_queries' => $this->to_nullable_int($this->array_value($database, 'slow_queries')),
			'running_queries' => $this->to_nullable_int($this->array_value($database, 'running_queries')),
			'threads' => $this->to_nullable_int($this->array_value($database, 'threads')),
			'uptime_seconds' => $this->to_nullable_int($this->array_value($database, 'uptime_seconds')),
			'last_backup' => $this->normalize_datetime($this->array_value($database, 'last_backup')),
			'logged_at' => $metric_time,
		));
	}

	protected function insert_website_logs($server_id, $metric_time, $websites)
	{
		foreach ($websites as $website)
		{
			$this->db->insert('website_logs', array(
				'server_id' => (int) $server_id,
				'domain' => $this->array_value($website, 'domain', 'unknown'),
				'status' => $this->normalize_online_status($this->array_value($website, 'status')),
				'http_status' => $this->to_nullable_int($this->array_value($website, 'http_status')),
				'response_time_ms' => $this->to_nullable_int($this->array_value($website, 'response_time_ms')),
				'ssl_expired_at' => $this->normalize_datetime($this->array_value($website, 'ssl_expired_at')),
				'ping_ms' => $this->to_nullable_float($this->array_value($website, 'ping_ms')),
				'dns_resolve_ms' => $this->to_nullable_float($this->array_value($website, 'dns_resolve_ms')),
				'last_check' => $this->normalize_datetime($this->array_value($website, 'last_check')) ?: $metric_time,
			));
		}
	}

	protected function insert_system_logs($server_id, $metric_time, $logs)
	{
		foreach ($logs as $log)
		{
			$source = $this->array_value($log, 'source');
			$message = trim((string) $this->array_value($log, 'message', ''));

			if ($message === '' || $this->system_log_exists($server_id, $source, $message))
			{
				continue;
			}

			$this->db->insert('system_logs', array(
				'server_id' => (int) $server_id,
				'log_type' => $this->array_value($log, 'log_type', $this->array_value($log, 'type', 'system')),
				'level' => $this->normalize_log_level($this->array_value($log, 'level')),
				'source' => $source,
				'message' => $message,
				'logged_at' => $this->normalize_datetime($this->array_value($log, 'logged_at')) ?: $metric_time,
			));
		}
	}

	protected function is_auth_failure($reason)
	{
		$reason = strtolower((string) $reason);
		$auth_errors = array(
			'auth',
			'login failed',
			'unable to login',
			'private key is empty',
			'private key tidak',
			'password required',
			'passphrase',
			'permission denied',
		);

		foreach ($auth_errors as $error)
		{
			if (strpos($reason, $error) !== FALSE)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	protected function insert_status_log($server_id, $level, $source, $message, $logged_at)
	{
		$message = trim((string) $message);

		if ($message === '' || $this->system_log_exists($server_id, $source, $message))
		{
			return FALSE;
		}

		return $this->db->insert('system_logs', array(
			'server_id' => (int) $server_id,
			'log_type' => 'ssh',
			'level' => $this->normalize_log_level($level),
			'source' => $source,
			'message' => $message,
			'logged_at' => $logged_at,
		));
	}

	protected function system_log_exists($server_id, $source, $message)
	{
		return (bool) $this->db
			->select('id')
			->from('system_logs')
			->where('server_id', (int) $server_id)
			->where('source', $source)
			->where('message', $message)
			->where('logged_at >=', date('Y-m-d H:i:s', time() - 86400))
			->limit(1)
			->get()
			->row();
	}

	protected function latest_rows($table, $time_field, $server_id, $limit)
	{
		if ( ! $server_id)
		{
			return array();
		}

		$latest = $this->latest_time($table, $time_field, $server_id);

		if ( ! $latest)
		{
			return array();
		}

		return $this->db
			->where('server_id', (int) $server_id)
			->where($time_field, $latest)
			->order_by('id', 'ASC')
			->limit($limit)
			->get($table)
			->result();
	}

	protected function latest_time($table, $time_field, $server_id)
	{
		$row = $this->db
			->select_max($time_field, 'latest_time')
			->where('server_id', (int) $server_id)
			->get($table)
			->row();

		return $row ? $row->latest_time : NULL;
	}

	protected function first_process_name($processes)
	{
		if (empty($processes) || ! is_array($processes))
		{
			return NULL;
		}

		$first = reset($processes);

		return is_array($first) ? $this->array_value($first, 'command') : NULL;
	}

	protected function array_value($array, $key, $default = NULL)
	{
		return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
	}

	protected function normalize_datetime($value)
	{
		if ( ! $value)
		{
			return NULL;
		}

		$timestamp = strtotime($value);

		if ($timestamp === FALSE)
		{
			return NULL;
		}

		return date('Y-m-d H:i:s', $timestamp);
	}

	protected function stringify($value)
	{
		if (is_array($value) || is_object($value))
		{
			return json_encode($value);
		}

		return $value;
	}

	protected function to_nullable_int($value)
	{
		return $value === NULL || $value === '' ? NULL : (int) $value;
	}

	protected function to_nullable_float($value)
	{
		return $value === NULL || $value === '' ? NULL : (float) $value;
	}

	protected function normalize_service_status($status)
	{
		return in_array($status, array('running', 'stopped'), TRUE) ? $status : 'unknown';
	}

	protected function normalize_online_status($status)
	{
		return in_array($status, array('online', 'offline'), TRUE) ? $status : 'unknown';
	}

	protected function normalize_log_level($level)
	{
		return in_array($level, array('debug', 'info', 'warning', 'error', 'critical'), TRUE) ? $level : 'info';
	}
}
