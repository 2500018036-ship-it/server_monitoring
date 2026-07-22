<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Service_manager extends MY_Controller
{
	protected $actions = array('start', 'stop', 'restart', 'reload', 'enable', 'disable', 'status');

	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin', 'Operator'));
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->library('Remote_ssh');
	}

	public function index()
	{
		$this->render('service_manager/index', array(
			'page_title' => 'Service Manager',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'services' => remote_service_names(),
			'actions' => $this->actions,
		));
	}

	public function action()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$service = $this->input->post('service_name', TRUE);
		$action = $this->input->post('service_action', TRUE);

		if ( ! $config || ! in_array($service, remote_service_names(), TRUE) || ! in_array($action, $this->actions, TRUE))
		{
			$this->session->set_flashdata('error', 'Service action tidak valid.');
			redirect('service-manager');
		}

		if (has_role(array('Operator')) && in_array($action, array('stop', 'disable'), TRUE))
		{
			show_error('Operator tidak boleh stop/disable service.', 403, 'Akses Ditolak');
		}

		$command = $action === 'status'
			? 'systemctl status '.remote_arg($service).' --no-pager -l'
			: 'sudo systemctl '.$action.' '.remote_arg($service).' && systemctl status '.remote_arg($service).' --no-pager -l';
		$result = $this->remote_ssh->execute($config, $command, 60);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Remote_history_model->service($config->server_id, $config->id, $this->current_user['id'], $service, $action, $status, $result['output']);
		$this->Activity_model->log($this->current_user['id'], ucfirst($action).' Service: '.$service, $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('service-manager');
	}
}
