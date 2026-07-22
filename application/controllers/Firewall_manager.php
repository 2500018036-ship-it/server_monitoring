<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Firewall_manager extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin'));
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->library('Remote_ssh');
	}

	public function index()
	{
		$this->render('firewall_manager/index', array(
			'page_title' => 'Firewall Manager',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'history' => $this->Remote_history_model->recent('firewall_history', 100),
		));
	}

	public function action()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$type = $this->input->post('firewall_type', TRUE);
		$action = $this->input->post('firewall_action', TRUE);
		$value = $this->input->post('rule_value', TRUE);

		if ( ! $config || ! in_array($type, array('ufw', 'iptables'), TRUE))
		{
			$this->session->set_flashdata('error', 'Firewall request tidak valid.');
			redirect('firewall-manager');
		}

		$commands = array(
			'ufw_status' => 'sudo ufw status verbose',
			'ufw_enable' => 'yes | sudo ufw enable',
			'ufw_disable' => 'sudo ufw disable',
			'ufw_allow_port' => 'sudo ufw allow '.remote_arg($value),
			'ufw_deny_port' => 'sudo ufw deny '.remote_arg($value),
			'ufw_allow_ip' => 'sudo ufw allow from '.remote_arg($value),
			'ufw_block_ip' => 'sudo ufw deny from '.remote_arg($value),
			'iptables_status' => 'sudo iptables -S',
		);
		$key = $type.'_'.$action;

		if ( ! isset($commands[$key]))
		{
			$this->session->set_flashdata('error', 'Firewall action tidak valid.');
			redirect('firewall-manager');
		}

		$result = $this->remote_ssh->execute($config, $commands[$key], 60);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Remote_history_model->firewall($config->server_id, $config->id, $this->current_user['id'], $type, $action, $value, $status, $result['output']);
		$this->Activity_model->log($this->current_user['id'], 'Firewall '.$type.' '.$action, $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('firewall-manager');
	}
}
