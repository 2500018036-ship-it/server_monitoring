<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller
{
	protected $json_payload = array();
	protected $api_key = NULL;
	protected $api_client_type = 'session';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Setting_model');
		$this->load->model('Monitoring_model');
		$this->json_payload = $this->read_json_payload();
	}

	public function server()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$payload = $this->Monitoring_model->dashboard_payload($server_id);

		$this->json_response($this->format_dashboard_payload($payload));
	}

	public function stream()
	{
		$this->authorize();

		if ( ! $this->is_session_authenticated())
		{
			$this->stream_headers(403);
			$this->stream_event('error', array('ok' => FALSE, 'message' => 'Session login diperlukan.'));
			exit;
		}

		$server_id = (int) $this->input->get('server_id', TRUE);
		$last_id = trim((string) $this->input->get('last_id', TRUE));
		$last_event_id = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? trim((string) $_SERVER['HTTP_LAST_EVENT_ID']) : '';

		if ($last_id === '' && $last_event_id !== '')
		{
			$last_id = $last_event_id;
		}

		$this->stream_headers();

		if (function_exists('session_write_close'))
		{
			session_write_close();
		}

		$started_at = time();
		$sent_first = FALSE;

		while ( ! connection_aborted())
		{
			$payload = $this->Monitoring_model->dashboard_payload($server_id);
			$stream_id = $this->stream_signature($payload);

			if ( ! $sent_first || $stream_id !== $last_id)
			{
				$this->stream_event('monitoring', $this->format_dashboard_payload($payload, array(
					'stream_id' => $stream_id,
					'stream_mode' => 'sse',
				)), $stream_id);
				$last_id = $stream_id;
				$sent_first = TRUE;
			}
			else
			{
				$this->stream_keepalive();
			}

			if ((time() - $started_at) >= 25)
			{
				break;
			}

			usleep(500000);
		}

		exit;
	}

	public function monitoring()
	{
		$this->authorize();

		if ( ! $this->is_session_authenticated())
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Session login diperlukan.'), 403);
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
			return;
		}

		$server_id = (int) $this->input->post('server_id', TRUE);
		$pull = $this->collect_ssh_metrics($server_id);
		$selected_server_id = $server_id ?: (! empty($pull['server_id']) ? (int) $pull['server_id'] : 0);
		$payload = $this->Monitoring_model->dashboard_payload($selected_server_id);

		$this->json_response($this->format_dashboard_payload($payload, array(
			'pull_ok' => (bool) $pull['ok'],
			'pull_status' => (int) $pull['status'],
			'pull_message' => $pull['message'],
			'csrf_hash' => $this->security->get_csrf_hash(),
		)));
	}

	public function pull_ssh_metrics()
	{
		$this->authorize();

		if ( ! $this->is_session_authenticated())
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Session login diperlukan.'), 403);
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
			return;
		}

		$server_id = (int) $this->input->post('server_id', TRUE);
		$ssh_config_id = (int) $this->input->post('ssh_config_id', TRUE);
		$pull = $this->collect_ssh_metrics($server_id, $ssh_config_id);

		$this->json_response(array(
			'ok' => (bool) $pull['ok'],
			'server_id' => $pull['server_id'],
			'message' => $pull['message'],
			'csrf_hash' => $this->security->get_csrf_hash(),
		), $pull['status']);
	}

	protected function collect_ssh_metrics($server_id = 0, $ssh_config_id = 0)
	{
		$this->load->model('Ssh_config_model');
		$this->load->library('Remote_metric_collector');
		$this->Monitoring_model->sync_ssh_config_servers();

		$server_id = (int) $server_id;
		$ssh_config_id = (int) $ssh_config_id;
		$config = $ssh_config_id ? $this->Ssh_config_model->find($ssh_config_id, TRUE) : NULL;

		if ($config && $config->status !== 'active')
		{
			$config = NULL;
		}

		if ( ! $config)
		{
			$config = $server_id ? $this->Ssh_config_model->find_active_by_server($server_id, TRUE) : NULL;
		}

		if ( ! $config && ! $server_id && ! $ssh_config_id)
		{
			$config = $this->Ssh_config_model->first_active(TRUE);
		}

		if ( ! $config)
		{
			return array(
				'ok' => FALSE,
				'status' => 404,
				'message' => 'Belum ada SSH Config aktif untuk pull monitoring.',
				'server_id' => $server_id,
			);
		}

		$target_server_id = ! empty($config->server_id) ? (int) $config->server_id : $server_id;
		$collected = $this->remote_metric_collector->collect($config);

		if ( ! $collected['ok'])
		{
			if ($target_server_id)
			{
				$this->Monitoring_model->record_connection_failure(
					$target_server_id,
					$this->ssh_status_failure_message($collected['message']),
					'ssh-pull'
				);
			}

			return array(
				'ok' => FALSE,
				'status' => 502,
				'message' => $collected['message'],
				'server_id' => $target_server_id,
			);
		}

		$preferred_server_id = $target_server_id ?: NULL;
		$saved_server_id = $this->Monitoring_model->upsert_server_from_payload($collected['payload'], 'ssh-pull', $preferred_server_id);
		$saved = $this->Monitoring_model->record_metrics($saved_server_id, $collected['payload'], $config->host);

		if ( ! $saved)
		{
			return array(
				'ok' => FALSE,
				'status' => 500,
				'message' => 'Metrics SSH tidak bisa disimpan.',
				'server_id' => $saved_server_id,
			);
		}

		if (empty($config->server_id) || (int) $config->server_id !== (int) $saved_server_id)
		{
			$this->Ssh_config_model->link_server($config->id, $saved_server_id);
		}

		$this->Ssh_config_model->touch_connected($config->id);

		return array(
			'ok' => TRUE,
			'status' => 201,
			'message' => 'Metrics SSH tersimpan.',
			'server_id' => $saved_server_id,
		);
	}

	protected function ssh_status_failure_message($message)
	{
		$message = trim((string) $message);

		if ($message === '')
		{
			return 'SSH gagal terkoneksi.';
		}

		return 'Status Offline: '.$message;
	}

	public function cpu()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$payload = $this->Monitoring_model->dashboard_payload($server_id);

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $payload['selected_server_id'],
			'cpu' => $this->safe_array_value($payload['payload'], 'cpu', array()),
			'history' => $payload['charts']['cpu'],
			'top_process' => $this->format_processes($payload['process_cpu']),
		));
	}

	public function ram()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$payload = $this->Monitoring_model->dashboard_payload($server_id);

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $payload['selected_server_id'],
			'memory' => $this->safe_array_value($payload['payload'], 'memory', array()),
			'history' => $payload['charts']['memory'],
			'top_process' => $this->format_processes($payload['process_memory']),
		));
	}

	public function storage()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$payload = $this->Monitoring_model->dashboard_payload($server_id);

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $payload['selected_server_id'],
			'storage' => $this->safe_array_value($payload['payload'], 'storage', array()),
			'history' => $payload['charts']['storage'],
		));
	}

	public function network()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$payload = $this->Monitoring_model->dashboard_payload($server_id);

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $payload['selected_server_id'],
			'network' => $this->safe_array_value($payload['payload'], 'network', array()),
			'upload_history' => $payload['charts']['network_upload'],
			'download_history' => $payload['charts']['network_download'],
		));
	}

	public function process()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$type = $this->input->get('type', TRUE);
		$search = $this->input->get('search', TRUE);
		$server_id = $server_id ?: $this->Monitoring_model->first_server_id();

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $server_id,
			'processes' => $this->format_processes($this->Monitoring_model->get_processes($server_id, $type, $search, 20)),
		));
	}

	public function service()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$server_id = $server_id ?: $this->Monitoring_model->first_server_id();

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $server_id,
			'services' => $this->format_services($this->Monitoring_model->get_services($server_id)),
		));
	}

	public function docker()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$server_id = $server_id ?: $this->Monitoring_model->first_server_id();

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $server_id,
			'containers' => $this->format_docker($this->Monitoring_model->get_docker($server_id)),
		));
	}

	public function database()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$server_id = $server_id ?: $this->Monitoring_model->first_server_id();

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $server_id,
			'database' => $this->format_database($this->Monitoring_model->get_database($server_id)),
		));
	}

	public function logs()
	{
		if ($this->input->method(TRUE) === 'POST')
		{
			$this->receive_logs();
			return;
		}

		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$server_id = $server_id ?: $this->Monitoring_model->first_server_id();
		$filters = array(
			'level' => $this->input->get('level', TRUE),
			'log_type' => $this->input->get('log_type', TRUE),
			'date' => $this->input->get('date', TRUE),
			'search' => $this->input->get('search', TRUE),
		);

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $server_id,
			'logs' => $this->format_logs($this->Monitoring_model->get_system_logs($server_id, $filters, 300)),
		));
	}

	public function system()
	{
		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$payload = $this->Monitoring_model->dashboard_payload($server_id);

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $payload['selected_server_id'],
			'server' => $this->format_server($payload['server']),
			'system' => $this->safe_array_value($payload['payload'], 'system', array()),
		));
	}

	public function heartbeat()
	{
		if ($this->input->method(TRUE) === 'POST')
		{
			$this->receive_heartbeat();
			return;
		}

		$this->authorize();
		$server_id = (int) $this->input->get('server_id', TRUE);
		$server_id = $server_id ?: $this->Monitoring_model->first_server_id();

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $server_id,
			'heartbeat' => $this->format_heartbeat($this->Monitoring_model->get_heartbeat($server_id)),
		));
	}

	public function metrics()
	{
		$this->authorize(TRUE);

		if ($this->input->method(TRUE) !== 'POST')
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
			return;
		}

		if (empty($this->json_payload))
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'JSON payload is required.'), 422);
			return;
		}

		$server_id = $this->Monitoring_model->upsert_server_from_payload($this->json_payload, $this->api_key);
		$saved = $this->Monitoring_model->record_metrics($server_id, $this->json_payload, $this->input->ip_address());

		if ( ! $saved)
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Metrics could not be saved.'), 500);
			return;
		}

		$this->json_response(array(
			'ok' => TRUE,
			'server_id' => $server_id,
			'message' => 'Metrics accepted.',
		), 201);
	}

	protected function receive_heartbeat()
	{
		$this->authorize(TRUE);
		$server_id = $this->Monitoring_model->upsert_server_from_payload($this->json_payload, $this->api_key);
		$this->Monitoring_model->record_heartbeat(
			$server_id,
			$this->safe_array_value($this->json_payload, 'response_time_ms'),
			$this->safe_array_value($this->json_payload, 'latency_ms'),
			$this->input->ip_address()
		);

		$this->json_response(array('ok' => TRUE, 'server_id' => $server_id, 'message' => 'Heartbeat accepted.'), 201);
	}

	protected function receive_logs()
	{
		$this->authorize(TRUE);
		$server_id = $this->Monitoring_model->upsert_server_from_payload($this->json_payload, $this->api_key);
		$logs = $this->safe_array_value($this->json_payload, 'logs', array());
		$this->Monitoring_model->record_logs($server_id, $logs);

		$this->json_response(array('ok' => TRUE, 'server_id' => $server_id, 'message' => 'Logs accepted.'), 201);
	}

	protected function authorize($require_api_key = FALSE)
	{
		if ( ! $require_api_key && $this->is_session_authenticated())
		{
			return TRUE;
		}

		$setting = $this->Setting_model->get_settings();
		$key = $this->extract_api_key();

		if ( ! $key || ! isset($setting->agent_api_key) || ! hash_equals((string) $setting->agent_api_key, (string) $key))
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Invalid API key.'), 401);
			exit;
		}

		$this->api_key = $key;
		$this->api_client_type = 'api_key';
		$this->enforce_origin($setting);
		$this->enforce_https($setting);
		$this->enforce_rate_limit($setting, $key);

		return TRUE;
	}

	protected function is_session_authenticated()
	{
		$user = $this->session->userdata('user');

		return is_array($user) && ! empty($user['id']);
	}

	protected function stream_headers($status = 200)
	{
		@ini_set('zlib.output_compression', '0');
		@ini_set('output_buffering', 'off');
		@ini_set('implicit_flush', '1');
		@set_time_limit(0);
		@ignore_user_abort(TRUE);

		while (ob_get_level() > 0)
		{
			@ob_end_flush();
		}

		$this->output->set_status_header($status);
		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache, no-transform');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');
		@ob_implicit_flush(TRUE);
	}

	protected function stream_event($event, $data, $id = NULL)
	{
		if ($id !== NULL)
		{
			echo 'id: '.$id."\n";
		}

		echo 'event: '.$event."\n";
		echo 'data: '.json_encode($data)."\n\n";
		@flush();
	}

	protected function stream_keepalive()
	{
		echo ': keepalive '.date('c')."\n\n";
		@flush();
	}

	protected function stream_signature($payload)
	{
		$server = isset($payload['server']) ? $payload['server'] : NULL;
		$metric = isset($payload['metric']) ? $payload['metric'] : NULL;
		$selected = isset($payload['selected_server_id']) ? (int) $payload['selected_server_id'] : 0;
		$metric_time = $metric && isset($metric->metric_time) ? $metric->metric_time : '';
		$updated_at = $server && isset($server->updated_at) ? $server->updated_at : '';
		$heartbeat = $server && isset($server->last_heartbeat_at) ? $server->last_heartbeat_at : '';

		return sha1($selected.'|'.$metric_time.'|'.$updated_at.'|'.$heartbeat);
	}

	protected function read_json_payload()
	{
		$raw = $this->input->raw_input_stream;

		if ( ! $raw)
		{
			return array();
		}

		$payload = json_decode($raw, TRUE);

		return is_array($payload) ? $payload : array();
	}

	protected function extract_api_key()
	{
		$header = $this->input->get_request_header('X-API-Key', TRUE);

		if ($header)
		{
			return trim($header);
		}

		$authorization = $this->input->get_request_header('Authorization', TRUE);
		if ( ! $authorization && isset($_SERVER['HTTP_AUTHORIZATION']))
		{
			$authorization = $_SERVER['HTTP_AUTHORIZATION'];
		}

		if ($authorization && stripos($authorization, 'Bearer ') === 0)
		{
			return trim(substr($authorization, 7));
		}

		$query_key = $this->input->get('api_key', TRUE);
		if ($query_key)
		{
			return trim($query_key);
		}

		return $this->safe_array_value($this->json_payload, 'api_key');
	}

	protected function enforce_origin($setting)
	{
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

		if ( ! $origin)
		{
			return TRUE;
		}

		$allowed = isset($setting->api_allowed_origins) ? trim((string) $setting->api_allowed_origins) : '';

		if ($allowed === '')
		{
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
			$origin_host = parse_url($origin, PHP_URL_HOST);

			if ($origin_host && $host && stripos($host, $origin_host) === 0)
			{
				return TRUE;
			}

			$this->json_response(array('ok' => FALSE, 'message' => 'Origin not allowed.'), 403);
			exit;
		}

		$origins = preg_split('/[\r\n,]+/', $allowed);
		$origins = array_map('trim', $origins);

		if ( ! in_array($origin, $origins, TRUE))
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Origin not allowed.'), 403);
			exit;
		}

		return TRUE;
	}

	protected function enforce_https($setting)
	{
		$host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
		$is_local = strpos($host, 'localhost') === 0 || strpos($host, '127.0.0.1') === 0;
		$is_https = ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';

		if ( ! $is_https && ! $is_local)
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'HTTPS is required for API access.'), 403);
			exit;
		}

		return TRUE;
	}

	protected function enforce_rate_limit($setting, $api_key)
	{
		$limit = isset($setting->api_rate_limit_per_minute) ? (int) $setting->api_rate_limit_per_minute : 1000;
		$limit = $limit > 0 ? $limit : 1000;
		$rate_key = hash('sha256', $api_key.'|'.$this->input->ip_address());

		if ( ! $this->Monitoring_model->increment_rate_limit($rate_key, $limit))
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Rate limit exceeded.'), 429);
			exit;
		}

		return TRUE;
	}

	protected function format_dashboard_payload($payload, $extra = array())
	{
		return array_merge(array(
			'ok' => TRUE,
			'servers' => $this->format_servers($payload['servers']),
			'selected_server_id' => $payload['selected_server_id'],
			'server' => $this->format_server($payload['server']),
			'metric' => $this->format_metric($payload['metric']),
			'metric_time' => $payload['metric'] ? $payload['metric']->metric_time : NULL,
			'payload' => $payload['payload'],
			'heartbeat' => $this->format_heartbeat($payload['heartbeat']),
			'alerts' => $payload['alerts'],
			'charts' => $payload['charts'],
			'process_cpu' => $this->format_processes($payload['process_cpu']),
			'process_memory' => $this->format_processes($payload['process_memory']),
			'services' => $this->format_services($payload['services']),
			'docker' => $this->format_docker($payload['docker']),
			'database' => $this->format_database($payload['database']),
			'websites' => $this->format_websites($payload['websites']),
			'logs' => $this->format_logs($payload['logs']),
		), $extra);
	}

	protected function format_servers($servers)
	{
		$items = array();

		foreach ($servers as $server)
		{
			$items[] = $this->format_server($server);
		}

		return $items;
	}

	protected function format_server($server)
	{
		if ( ! $server)
		{
			return NULL;
		}

		return array(
			'id' => (int) $server->id,
			'server_name' => $server->server_name,
			'hostname' => $server->hostname,
			'provider' => $server->provider,
			'public_ip' => $server->public_ip,
			'private_ip' => $server->private_ip,
			'operating_system' => $server->operating_system,
			'kernel' => isset($server->kernel) ? $server->kernel : NULL,
			'architecture' => isset($server->architecture) ? $server->architecture : NULL,
			'uptime_seconds' => isset($server->uptime_seconds) ? (int) $server->uptime_seconds : 0,
			'current_time' => isset($server->current_time) ? $server->current_time : NULL,
			'timezone' => isset($server->timezone) ? $server->timezone : NULL,
			'azure_region' => isset($server->azure_region) ? $server->azure_region : NULL,
			'status' => isset($server->monitoring_status) ? $server->monitoring_status : 'offline',
			'health_status' => isset($server->health_status) ? $server->health_status : (isset($server->monitoring_status) ? $server->monitoring_status : 'offline'),
			'health_label' => isset($server->health_label) ? $server->health_label : ucfirst(isset($server->monitoring_status) ? $server->monitoring_status : 'offline'),
			'health_badge' => isset($server->health_badge) ? $server->health_badge : ((isset($server->monitoring_status) && $server->monitoring_status === 'online') ? 'success' : 'danger'),
			'health_reason' => isset($server->health_reason) ? $server->health_reason : '',
			'health_summary' => isset($server->health_summary) ? $server->health_summary : '',
			'last_seen_text' => isset($server->last_seen_text) ? $server->last_seen_text : '',
			'inventory_status' => $server->status,
			'last_heartbeat_at' => isset($server->last_heartbeat_at) ? $server->last_heartbeat_at : NULL,
			'last_latency_ms' => isset($server->last_latency_ms) ? $server->last_latency_ms : NULL,
			'last_response_time_ms' => isset($server->last_response_time_ms) ? $server->last_response_time_ms : NULL,
		);
	}

	protected function format_metric($metric)
	{
		if ( ! $metric)
		{
			return NULL;
		}

		return array(
			'id' => (int) $metric->id,
			'metric_time' => $metric->metric_time,
			'cpu_usage' => $metric->cpu_usage === NULL ? NULL : (float) $metric->cpu_usage,
			'memory_usage' => $metric->memory_usage === NULL ? NULL : (float) $metric->memory_usage,
			'disk_usage' => $metric->disk_usage === NULL ? NULL : (float) $metric->disk_usage,
			'upload_speed' => (int) $metric->upload_speed,
			'download_speed' => (int) $metric->download_speed,
			'active_connections' => (int) $metric->active_connections,
		);
	}

	protected function format_processes($processes)
	{
		$items = array();

		foreach ($processes as $process)
		{
			$items[] = array(
				'pid' => (int) $process->pid,
				'user' => $process->user,
				'command' => $process->command,
				'cpu' => $process->cpu_percent === NULL ? NULL : (float) $process->cpu_percent,
				'ram' => $process->ram_percent === NULL ? NULL : (float) $process->ram_percent,
				'running_time' => $process->running_time,
				'type' => $process->process_type,
			);
		}

		return $items;
	}

	protected function format_services($services)
	{
		$items = array();

		foreach ($services as $service)
		{
			$items[] = array(
				'name' => $service->service_name,
				'status' => $service->status,
				'action' => $service->action,
				'log_excerpt' => $service->log_excerpt,
				'logged_at' => $service->logged_at,
			);
		}

		return $items;
	}

	protected function format_docker($containers)
	{
		$items = array();

		foreach ($containers as $container)
		{
			$items[] = array(
				'container_id' => $container->container_id,
				'container_name' => $container->container_name,
				'image' => $container->image,
				'status' => $container->status,
				'cpu' => $container->cpu_percent === NULL ? NULL : (float) $container->cpu_percent,
				'ram' => $container->ram_usage,
				'restart_count' => $container->restart_count,
				'ports' => $container->ports,
				'network' => $container->network,
				'volume' => $container->volume,
				'logged_at' => $container->logged_at,
			);
		}

		return $items;
	}

	protected function format_database($rows)
	{
		$items = array();

		foreach ($rows as $row)
		{
			$items[] = array(
				'engine' => $row->engine,
				'status' => $row->status,
				'connection_status' => $row->connection_status,
				'database_size_mb' => $row->database_size_mb === NULL ? NULL : (float) $row->database_size_mb,
				'slow_queries' => $row->slow_queries,
				'running_queries' => $row->running_queries,
				'threads' => $row->threads,
				'uptime_seconds' => $row->uptime_seconds,
				'last_backup' => $row->last_backup,
				'logged_at' => $row->logged_at,
			);
		}

		return $items;
	}

	protected function format_websites($rows)
	{
		$items = array();

		foreach ($rows as $row)
		{
			$items[] = array(
				'domain' => $row->domain,
				'status' => $row->status,
				'http_status' => $row->http_status,
				'response_time_ms' => $row->response_time_ms,
				'ssl_expired_at' => $row->ssl_expired_at,
				'ping_ms' => $row->ping_ms,
				'dns_resolve_ms' => $row->dns_resolve_ms,
				'last_check' => $row->last_check,
			);
		}

		return $items;
	}

	protected function format_heartbeat($rows)
	{
		$items = array();

		foreach ($rows as $row)
		{
			$items[] = array(
				'heartbeat_at' => $row->heartbeat_at,
				'response_time_ms' => $row->response_time_ms,
				'latency_ms' => $row->latency_ms,
				'ip_address' => $row->ip_address,
			);
		}

		return $items;
	}

	protected function format_logs($logs)
	{
		$items = array();

		foreach ($logs as $log)
		{
			$items[] = array(
				'id' => (int) $log->id,
				'log_type' => $log->log_type,
				'level' => $log->level,
				'source' => $log->source,
				'message' => $log->message,
				'logged_at' => $log->logged_at,
			);
		}

		return $items;
	}

	protected function safe_array_value($array, $key, $default = NULL)
	{
		return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
	}

	protected function json_response($payload, $status = 200)
	{
		$this->output
			->set_status_header($status)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($payload));
	}
}
