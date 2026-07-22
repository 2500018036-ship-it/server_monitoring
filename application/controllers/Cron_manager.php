<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron_manager extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->library('Remote_ssh');
	}

	public function index()
	{
		$this->render('cron_manager/index', array(
			'page_title' => 'Cron Manager',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'history' => $this->Remote_history_model->recent('cron_history', 100),
		));
	}

	public function action()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$action = $this->input->post('cron_action', TRUE);
		$expression = $this->input->post('cron_expression', TRUE);
		$command_text = $this->input->post('command', FALSE);

		if ( ! $config)
		{
			$this->session->set_flashdata('error', 'SSH config tidak valid.');
			redirect('cron-manager');
		}

		if ($action === 'list')
		{
			$command = 'crontab -l';
		}
		elseif ($action === 'add' && $expression && $command_text)
		{
			$new_line = $expression.' '.$command_text;
			$command = '(crontab -l 2>/dev/null; printf "%s\n" '.remote_arg($new_line).') | crontab -';
		}
		elseif ($action === 'run' && $command_text)
		{
			$command = $command_text;
		}
		else
		{
			$this->session->set_flashdata('error', 'Cron action tidak valid.');
			redirect('cron-manager');
		}

		$result = $this->remote_ssh->execute($config, $command, 120);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Remote_history_model->cron($config->server_id, $config->id, $this->current_user['id'], $action, $expression, $command_text, TRUE, $status, $result['output']);
		$this->Activity_model->log($this->current_user['id'], 'Cron '.$action, $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('cron-manager');
	}
}
