<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System_manager extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Ssh_config_model');
		$this->load->library('Remote_ssh');
	}

	public function index()
	{
		$this->render('system_manager/index', array(
			'page_title' => 'System Manager',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
		));
	}

	public function action()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$action = $this->input->post('system_action', TRUE);
		$super_only = array('reboot', 'shutdown');

		if ( ! $config)
		{
			$this->session->set_flashdata('error', 'SSH config tidak valid.');
			redirect('system-manager');
		}

		if (in_array($action, $super_only, TRUE) && ! has_role(array('Super Admin')))
		{
			show_error('Aksi sensitif hanya untuk Super Admin.', 403, 'Akses Ditolak');
		}

		$commands = array(
			'info' => 'hostnamectl; echo "--- mounted disk ---"; df -h; echo "--- usb ---"; lsusb 2>/dev/null; echo "--- cpu ---"; lscpu; echo "--- memory ---"; free -h',
			'reboot' => 'sudo reboot',
			'shutdown' => 'sudo shutdown -h now',
			'update' => 'sudo apt-get update',
			'upgrade' => 'sudo DEBIAN_FRONTEND=noninteractive apt-get -y upgrade',
			'clean_cache' => 'sudo apt-get clean && sudo apt-get autoremove -y',
		);

		if ( ! isset($commands[$action]))
		{
			$this->session->set_flashdata('error', 'System action tidak valid.');
			redirect('system-manager');
		}

		$result = $this->remote_ssh->execute($config, $commands[$action], 240);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Activity_model->log($this->current_user['id'], 'System '.$action, $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('system-manager');
	}
}
