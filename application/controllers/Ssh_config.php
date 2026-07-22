<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ssh_config extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Ssh_config_model');
		$this->load->model('Server_model');
		$this->load->model('Monitoring_model');
		$this->load->library('Remote_ssh');
		$this->load->helper('remote');
	}

	public function index()
	{
		$servers = $this->Monitoring_model->get_servers();

		$this->render('ssh_config/index', array(
			'page_title' => 'SSH Config',
			'configs' => $this->Ssh_config_model->get_all(),
			'servers' => $servers,
			'phpseclib_available' => $this->remote_ssh->available(),
		));
	}

	public function store()
	{
		$this->require_post();
		$this->validate_form(TRUE);

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('ssh-config');
		}

		$data = $this->input_data(TRUE);
		$id = $this->Ssh_config_model->create($data);
		$this->record_activity('Tambah SSH Config: '.$data['name']);
		$this->session->set_flashdata('success', 'SSH configuration berhasil disimpan.');

		redirect('ssh-config');
	}

	public function update($id)
	{
		$this->require_post();
		$id = (int) $id;

		$config = $this->Ssh_config_model->find($id, TRUE);

		if ( ! $config)
		{
			show_404();
		}

		$this->validate_form(FALSE, $config);

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('ssh-config');
		}

		$data = $this->input_data(FALSE);
		$this->Ssh_config_model->update($id, $data);
		$this->record_activity('Edit SSH Config: '.$data['name']);
		$this->session->set_flashdata('success', 'SSH configuration berhasil diperbarui.');

		redirect('ssh-config');
	}

	public function delete($id)
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $id, TRUE);

		if ( ! $config)
		{
			show_404();
		}

		$cleanup = $this->remove_remote_agent($config);
		$result = $this->Ssh_config_model->delete_with_server($config->id);
		$this->record_activity('Delete SSH Config: '.$config->name);

		if ($result['ok'])
		{
			$message = 'SSH configuration dan server monitoring berhasil dihapus.';
			if ( ! $cleanup['ok'])
			{
				$message .= ' Local data sudah dihapus, tetapi remote agent tidak bisa dibersihkan otomatis: '.$cleanup['message'];
			}
			$this->session->set_flashdata('success', html_escape($message));
		}
		else
		{
			$this->session->set_flashdata('error', 'SSH configuration gagal dihapus.');
		}

		redirect('ssh-config');
	}

	public function test($id)
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $id, TRUE);

		if ( ! $config)
		{
			show_404();
		}

		$result = $this->remote_ssh->test($config);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Activity_model->log($this->current_user['id'], 'Test SSH Connection: '.$config->name, $this->input->ip_address(), $config->server_id, $status, $result['output']);

		if ($result['ok'])
		{
			$this->Ssh_config_model->touch_connected($config->id);
			$this->session->set_flashdata('success', 'Connection OK: '.html_escape($result['output']));
		}
		else
		{
			$this->session->set_flashdata('error', 'Connection gagal: '.html_escape($result['output']));
		}

		redirect('ssh-config');
	}

	public function install_agent($id)
	{
		$this->require_post();
		$this->Monitoring_model->sync_ssh_config_servers();

		$config = $this->Ssh_config_model->find((int) $id, TRUE);

		if ( ! $config)
		{
			show_404();
		}

		if ($config->status !== 'active')
		{
			$this->session->set_flashdata('error', 'SSH Config tidak aktif. Aktifkan dulu sebelum install realtime agent.');
			redirect('ssh-config');
		}

		$this->load->library('Remote_agent_provisioner');
		$result = $this->remote_agent_provisioner->install($config);
		$status = $result['ok'] ? 'success' : 'failed';
		$details = $this->activity_details($result);

		$this->Activity_model->log(
			$this->current_user['id'],
			'Install/Repair Realtime Agent: '.$config->name,
			$this->input->ip_address(),
			$config->server_id,
			$status,
			$details
		);

		if ($result['ok'])
		{
			$this->Ssh_config_model->touch_connected($config->id);
			$this->session->set_flashdata(
				'success',
				html_escape($result['message']).'<br><small>Endpoint: '.html_escape($result['metrics_url']).'</small>'
			);
		}
		else
		{
			$this->session->set_flashdata(
				'error',
				html_escape($result['message']).'<br><small>Endpoint: '.html_escape($result['metrics_url']).'</small>'
			);
		}

		redirect('ssh-config');
	}

	protected function validate_form($is_create, $config = NULL)
	{
		$this->form_validation->set_rules('name', 'Name', 'required|trim|min_length[3]');
		$this->form_validation->set_rules('host', 'Host', 'required|trim');
		$this->form_validation->set_rules('port', 'Port', 'required|integer|greater_than[0]|less_than[65536]');
		$this->form_validation->set_rules('username', 'Username', 'required|trim');
		$this->form_validation->set_rules('auth_type', 'Authentication Type', 'required|in_list[password,private_key]');
		$this->form_validation->set_rules('status', 'Status', 'required|in_list[active,inactive]');

		if ($is_create && $this->input->post('auth_type') === 'password')
		{
			$this->form_validation->set_rules('password', 'Password', 'required');
		}

		if ($is_create && $this->input->post('auth_type') === 'private_key')
		{
			$this->form_validation->set_rules('private_key', 'Private Key', 'required');
		}

		if ( ! $is_create && $this->input->post('auth_type') === 'private_key')
		{
			$posted_key = trim((string) $this->input->post('private_key', FALSE));

			if ($posted_key === '' && ( ! $config || empty($config->private_key)))
			{
				$this->form_validation->set_rules(
					'private_key',
					'Private Key',
					'required',
					array('required' => 'Private key lama tidak bisa dibaca. Paste dan simpan ulang private key.')
				);
			}
		}
	}

	protected function input_data($is_create)
	{
		return array(
			'server_id' => $this->input->post('server_id', TRUE),
			'name' => $this->input->post('name', TRUE),
			'host' => $this->input->post('host', TRUE),
			'port' => $this->input->post('port', TRUE),
			'username' => $this->input->post('username', TRUE),
			'auth_type' => $this->input->post('auth_type', TRUE),
			'password' => $this->input->post('password', FALSE),
			'private_key' => $this->input->post('private_key', FALSE),
			'passphrase' => $this->input->post('passphrase', FALSE),
			'status' => $this->input->post('status', TRUE),
			'created_by' => $this->current_user['id'],
		);
	}

	protected function activity_details($result)
	{
		$details = 'Endpoint: '.(isset($result['metrics_url']) ? $result['metrics_url'] : '-');

		if (isset($result['agent_id']))
		{
			$details .= "\nAgent ID: ".$result['agent_id'];
		}

		if (isset($result['server_name']))
		{
			$details .= "\nServer: ".$result['server_name'];
		}

		if (isset($result['output']) && trim((string) $result['output']) !== '')
		{
			$details .= "\n\n".$result['output'];
		}

		if (strlen($details) > 8000)
		{
			$details = substr($details, 0, 8000)."\n...[truncated]";
		}

		return $details;
	}

	protected function remove_remote_agent($config)
	{
		$sudo = $this->sudo_prefix($config);

		if ($sudo === FALSE)
		{
			return array('ok' => FALSE, 'message' => 'Tidak bisa mendapatkan akses sudo ke server.');
		}

		$command = implode("\n", array(
			'if command -v systemctl >/dev/null 2>&1; then',
			'  '.$sudo.'systemctl disable --now monitoring-agent >/dev/null 2>&1 || true',
			'fi',
			$sudo.'rm -f /etc/systemd/system/monitoring-agent.service /etc/server-monitoring-agent.env',
			$sudo.'rm -rf /opt/server-monitoring-agent',
			'if command -v systemctl >/dev/null 2>&1; then',
			'  '.$sudo.'systemctl daemon-reload >/dev/null 2>&1 || true',
			'fi',
			'echo "monitoring-agent removed"',
		));
		$result = $this->remote_ssh->execute($config, $command, 30);

		return array(
			'ok' => (bool) $result['ok'],
			'message' => trim((string) $result['output']) ?: ($result['ok'] ? 'Remote agent removed.' : 'Remote cleanup failed.'),
		);
	}

	protected function sudo_prefix($config)
	{
		$uid = $this->remote_ssh->execute($config, 'id -u', 10);

		if ( ! $uid['ok'])
		{
			return FALSE;
		}

		if (trim((string) $uid['output']) === '0')
		{
			return '';
		}

		$sudo = $this->remote_ssh->execute($config, 'sudo -n true', 10);
		if ($sudo['ok'])
		{
			return 'sudo -n ';
		}

		if (isset($config->auth_type) && $config->auth_type === 'password' && ! empty($config->password))
		{
			$password_sudo = 'printf '.remote_arg('%s\n').' '.remote_arg($config->password).' | sudo -S -p '.remote_arg(''). ' ';
			$check = $this->remote_ssh->execute($config, $password_sudo.'true', 10);

			if ($check['ok'])
			{
				return $password_sudo;
			}
		}

		return FALSE;
	}
}
