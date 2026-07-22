<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Terminal extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Monitoring_model');
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->library('Remote_ssh');
	}

	public function index()
	{
		$this->render('terminal/index', array(
			'page_title' => 'Terminal',
			'servers' => $this->Monitoring_model->get_servers(),
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'phpseclib_available' => $this->remote_ssh->available(),
		));
	}

	public function execute()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$command = trim((string) $this->input->post('command', FALSE));
		$started_at = date('Y-m-d H:i:s');

		if ( ! $config || $config->status !== 'active')
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'SSH config tidak aktif.'));
			return;
		}

		if ($command === '')
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Command wajib diisi.'));
			return;
		}

		$result = $this->remote_ssh->execute($config, $command, 60);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Remote_history_model->terminal($config->id, $this->current_user['id'], $command, $result['output'], $result['exit_status'], $status, $started_at);
		$this->Activity_model->log($this->current_user['id'], 'Terminal Command: '.$command, $this->input->ip_address(), $config->server_id, $status, $result['output']);

		$this->json_response(array(
			'ok' => $result['ok'],
			'output' => $result['output'],
			'exit_status' => $result['exit_status'],
			'csrf_hash' => $this->security->get_csrf_hash(),
		));
	}

	protected function json_response($payload)
	{
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($payload));
	}
}
