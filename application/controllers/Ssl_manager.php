<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ssl_manager extends MY_Controller
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
		$this->render('ssl_manager/index', array(
			'page_title' => 'SSL Manager',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'history' => $this->Remote_history_model->recent('ssl_history', 100),
		));
	}

	public function action()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$domain = $this->input->post('domain', TRUE);
		$action = $this->input->post('ssl_action', TRUE);

		if ( ! $config || $domain === '' || ! in_array($action, array('check', 'renew', 'force_renew'), TRUE))
		{
			$this->session->set_flashdata('error', 'SSL action tidak valid.');
			redirect('ssl-manager');
		}

		$commands = array(
			'check' => 'echo | openssl s_client -servername '.remote_arg($domain).' -connect '.remote_arg($domain.':443').' 2>/dev/null | openssl x509 -noout -dates',
			'renew' => 'sudo certbot renew',
			'force_renew' => 'sudo certbot renew --force-renewal -d '.remote_arg($domain),
		);
		$result = $this->remote_ssh->execute($config, $commands[$action], 180);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Remote_history_model->ssl($config->server_id, $config->id, $this->current_user['id'], $domain, $action, $status, $result['output']);
		$this->Activity_model->log($this->current_user['id'], 'SSL '.$action.': '.$domain, $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('ssl-manager');
	}
}
