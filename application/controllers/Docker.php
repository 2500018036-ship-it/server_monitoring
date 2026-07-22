<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Docker extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin', 'Operator'));
		$this->load->model('Monitoring_model');
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->library('Remote_ssh');
	}

	public function index()
	{
		$server_id = (int) $this->input->get('server_id', TRUE);

		$this->render('docker/index', array(
			'page_title' => 'Docker',
			'monitoring' => $this->Monitoring_model->dashboard_payload($server_id),
			'configs' => $this->Ssh_config_model->get_all(TRUE),
		));
	}

	public function action()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$action = $this->input->post('docker_action', TRUE);
		$target = $this->input->post('target', TRUE);
		$allowed = array('start', 'stop', 'restart', 'logs', 'pull_image', 'remove_image');

		if ( ! $config || ! in_array($action, $allowed, TRUE) || $target === '')
		{
			$this->session->set_flashdata('error', 'Docker action tidak valid.');
			redirect('docker');
		}

		$command_map = array(
			'start' => 'sudo docker start '.remote_arg($target),
			'stop' => 'sudo docker stop '.remote_arg($target),
			'restart' => 'sudo docker restart '.remote_arg($target),
			'logs' => 'sudo docker logs --tail 200 '.remote_arg($target),
			'pull_image' => 'sudo docker pull '.remote_arg($target),
			'remove_image' => 'sudo docker rmi '.remote_arg($target),
		);
		$result = $this->remote_ssh->execute($config, $command_map[$action], 120);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Activity_model->log($this->current_user['id'], 'Docker '.$action.': '.$target, $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('docker');
	}
}
