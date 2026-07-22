<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Remote_agent_provisioner
{
	protected $CI;
	protected $remote_tmp_base = '/tmp/server-monitoring-agent';
	protected $agent_dir = '/opt/server-monitoring-agent';
	protected $env_path = '/etc/server-monitoring-agent.env';
	protected $service_path = '/etc/systemd/system/monitoring-agent.service';

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->helper('remote');
		$this->CI->load->library('Remote_ssh');
		$this->CI->load->model('Setting_model');
	}

	public function metrics_url()
	{
		$override = getenv('AGENT_PUBLIC_BASE_URL');

		if ($override === FALSE || trim((string) $override) === '')
		{
			$override = getenv('APP_PUBLIC_BASE_URL');
		}

		if ($override !== FALSE && trim((string) $override) !== '')
		{
			return $this->append_metrics_path(trim((string) $override));
		}

		return site_url('api/metrics');
	}

	public function install($config)
	{
		$metrics_url = $this->metrics_url();
		$url_error = $this->validate_metrics_url($metrics_url);

		if ($url_error)
		{
			return array(
				'ok' => FALSE,
				'message' => $url_error,
				'output' => $url_error,
				'metrics_url' => $metrics_url,
			);
		}

		$agent_path = FCPATH.'agent/monitoring_agent.py';
		$service_template_path = FCPATH.'agent/monitoring-agent.service';

		if ( ! is_file($agent_path))
		{
			return $this->failed('File agent tidak ditemukan: agent/monitoring_agent.py', $metrics_url);
		}

		if ( ! is_file($service_template_path))
		{
			return $this->failed('File service agent tidak ditemukan: agent/monitoring-agent.service', $metrics_url);
		}

		$setting = $this->CI->Setting_model->get_settings();
		$server = $this->linked_server($config);
		$agent_id = $this->agent_id($config, $server);
		$server_name = $this->server_name($config, $server);
		$tmp_dir = $this->remote_tmp_base.'-'.(int) $config->id.'-'.time();
		$output = array();

		$sudo = $this->sudo_prefix($config, $output);
		if ($sudo === FALSE)
		{
			return array(
				'ok' => FALSE,
				'message' => 'User SSH tidak punya akses sudo tanpa password. Gunakan user root atau user sudo NOPASSWD untuk auto install agent.',
				'output' => trim(implode("\n", $output)),
				'metrics_url' => $metrics_url,
			);
		}

		$prepare = $this->CI->remote_ssh->execute(
			$config,
			'mkdir -p '.remote_arg($tmp_dir).' && chmod 700 '.remote_arg($tmp_dir),
			15
		);
		$output[] = $this->format_result('prepare temp folder', $prepare);

		if ( ! $prepare['ok'])
		{
			return $this->failed('Gagal membuat temp folder di VPS.', $metrics_url, $output);
		}

		$env_content = $this->env_content($setting, $metrics_url, $agent_id, $server_name);
		$uploads = array(
			array('local' => $agent_path, 'remote' => $tmp_dir.'/monitoring_agent.py', 'type' => 'file'),
			array('local' => $service_template_path, 'remote' => $tmp_dir.'/monitoring-agent.service', 'type' => 'file'),
			array('content' => $env_content, 'remote' => $tmp_dir.'/server-monitoring-agent.env', 'type' => 'content'),
		);

		foreach ($uploads as $upload)
		{
			$result = $upload['type'] === 'content'
				? $this->CI->remote_ssh->write_file($config, $upload['remote'], $upload['content'])
				: $this->CI->remote_ssh->upload($config, $upload['remote'], $upload['local']);

			$output[] = $this->format_file_result('upload '.$upload['remote'], $result);

			if ( ! $result['ok'])
			{
				return $this->failed('Gagal upload file agent ke VPS: '.$result['message'], $metrics_url, $output);
			}
		}

		$install = $this->CI->remote_ssh->execute($config, $this->install_command($tmp_dir, $sudo), 60);
		$output[] = $this->format_result('install/restart service', $install);

		if ( ! $install['ok'])
		{
			return $this->failed('Agent gagal dipasang atau service tidak aktif. Cek Activity Log untuk detail journal.', $metrics_url, $output);
		}

		$this->cleanup($config, $tmp_dir);

		return array(
			'ok' => TRUE,
			'message' => 'Realtime agent berhasil dipasang dan service monitoring-agent aktif. Tunggu 1-3 detik sampai heartbeat masuk ke dashboard.',
			'output' => trim(implode("\n\n", $output)),
			'metrics_url' => $metrics_url,
			'agent_id' => $agent_id,
			'server_name' => $server_name,
		);
	}

	protected function append_metrics_path($base)
	{
		if (preg_match('#/api/metrics/?$#', $base))
		{
			return rtrim($base, '/');
		}

		$index_page = trim((string) $this->CI->config->item('index_page'), '/');

		if ($index_page !== '' && preg_match('#/'.preg_quote($index_page, '#').'/?$#', $base))
		{
			return rtrim($base, '/').'/api/metrics';
		}

		$path = $index_page === '' ? 'api/metrics' : $index_page.'/api/metrics';

		return rtrim($base, '/').'/'.$path;
	}

	protected function validate_metrics_url($url)
	{
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		$host = strtolower((string) parse_url($url, PHP_URL_HOST));

		if ( ! in_array($scheme, array('http', 'https'), TRUE) || $host === '')
		{
			return 'URL endpoint agent tidak valid. Set APP_BASE_URL atau AGENT_PUBLIC_BASE_URL dengan URL http/https yang benar.';
		}

		if (in_array($host, array('localhost', '127.0.0.1', '::1', '0.0.0.0'), TRUE))
		{
			return 'Endpoint agent masih memakai localhost. VPS tidak bisa mengirim heartbeat ke localhost laptop. Set APP_BASE_URL atau AGENT_PUBLIC_BASE_URL ke domain/IP publik aplikasi monitoring dulu.';
		}

		return NULL;
	}

	protected function linked_server($config)
	{
		if (empty($config->server_id))
		{
			return NULL;
		}

		return $this->CI->db
			->where('id', (int) $config->server_id)
			->get('servers')
			->row();
	}

	protected function agent_id($config, $server)
	{
		if ($server && ! empty($server->agent_id))
		{
			return $server->agent_id;
		}

		return 'ssh-config-'.(int) $config->id;
	}

	protected function server_name($config, $server)
	{
		if ($server && ! empty($server->server_name))
		{
			return $server->server_name;
		}

		return ! empty($config->name) ? $config->name : 'SSH Server '.(int) $config->id;
	}

	protected function env_content($setting, $metrics_url, $agent_id, $server_name)
	{
		$verify_tls = strtolower((string) parse_url($metrics_url, PHP_URL_SCHEME)) === 'https' ? '1' : '0';
		$services = implode(',', remote_service_names());

		$lines = array(
			'SM_API_URL' => $metrics_url,
			'SM_API_KEY' => isset($setting->agent_api_key) ? $setting->agent_api_key : '',
			'SM_AGENT_ID' => $agent_id,
			'SM_SERVER_NAME' => $server_name,
			'SM_PROVIDER' => 'Linux VPS',
			'SM_AZURE_REGION' => '',
			'SM_INTERVAL' => '1',
			'SM_VERIFY_TLS' => $verify_tls,
			'SM_WEBSITES' => '',
			'SM_SERVICES' => $services,
		);

		$content = "# Auto generated by Server Monitoring\n";
		foreach ($lines as $key => $value)
		{
			$content .= $key.'='.$this->env_quote($value)."\n";
		}

		return $content;
	}

	protected function env_quote($value)
	{
		$value = str_replace(array('\\', '"', '$', '`'), array('\\\\', '\\"', '\\$', '\\`'), (string) $value);

		return '"'.$value.'"';
	}

	protected function sudo_prefix($config, &$output)
	{
		$uid = $this->CI->remote_ssh->execute($config, 'id -u', 10);
		$output[] = $this->format_result('detect remote user', $uid);

		if ( ! $uid['ok'])
		{
			return FALSE;
		}

		if (trim((string) $uid['output']) === '0')
		{
			return '';
		}

		$sudo = $this->CI->remote_ssh->execute($config, 'sudo -n true', 10);
		$output[] = $this->format_result('check sudo nopasswd', $sudo);

		return $sudo['ok'] ? 'sudo -n ' : FALSE;
	}

	protected function install_command($tmp_dir, $sudo)
	{
		$agent_source = remote_arg($tmp_dir.'/monitoring_agent.py');
		$service_source = remote_arg($tmp_dir.'/monitoring-agent.service');
		$env_source = remote_arg($tmp_dir.'/server-monitoring-agent.env');
		$agent_target = remote_arg($this->agent_dir.'/monitoring_agent.py');
		$service_target = remote_arg($this->service_path);
		$env_target = remote_arg($this->env_path);
		$agent_dir = remote_arg($this->agent_dir);

		return implode("\n", array(
			'set -e',
			'if ! command -v systemctl >/dev/null 2>&1; then echo "systemctl tidak tersedia di server ini."; exit 1; fi',
			'if ! command -v python3 >/dev/null 2>&1; then echo "python3 tidak tersedia di server ini."; exit 1; fi',
			$sudo.'mkdir -p '.$agent_dir,
			$sudo.'cp '.$agent_source.' '.$agent_target,
			$sudo.'cp '.$env_source.' '.$env_target,
			$sudo.'cp '.$service_source.' '.$service_target,
			$sudo.'chmod 0755 '.$agent_target,
			$sudo.'chmod 0600 '.$env_target,
			$sudo.'chmod 0644 '.$service_target,
			$sudo.'systemctl daemon-reload',
			$sudo.'systemctl enable monitoring-agent',
			$sudo.'systemctl restart monitoring-agent',
			'sleep 2',
			'if '.$sudo.'systemctl is-active --quiet monitoring-agent; then',
			'  echo "monitoring-agent active"',
			'else',
			'  echo "monitoring-agent failed"',
			'  '.$sudo.'journalctl -u monitoring-agent -n 40 --no-pager || true',
			'  exit 1',
			'fi',
			$sudo.'systemctl --no-pager --full status monitoring-agent || true',
		));
	}

	protected function cleanup($config, $tmp_dir)
	{
		if (strpos($tmp_dir, $this->remote_tmp_base.'-') !== 0)
		{
			return FALSE;
		}

		$this->CI->remote_ssh->execute($config, 'rm -rf -- '.remote_arg($tmp_dir), 10);

		return TRUE;
	}

	protected function failed($message, $metrics_url, $output = array())
	{
		return array(
			'ok' => FALSE,
			'message' => $message,
			'output' => trim(implode("\n\n", $output)),
			'metrics_url' => $metrics_url,
		);
	}

	protected function format_result($label, $result)
	{
		$exit = isset($result['exit_status']) && $result['exit_status'] !== NULL ? $result['exit_status'] : '-';
		$output = trim(isset($result['output']) ? (string) $result['output'] : '');

		return '['.$label.'] '.($result['ok'] ? 'OK' : 'FAILED').' exit='.$exit."\n".$output;
	}

	protected function format_file_result($label, $result)
	{
		return '['.$label.'] '.($result['ok'] ? 'OK' : 'FAILED').' '.(isset($result['message']) ? $result['message'] : '');
	}
}
