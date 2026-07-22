<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quick_actions extends MY_Controller
{
	protected $actions = array();

	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin', 'Operator'));
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->library('Remote_ssh');
		$this->actions = $this->action_map();
	}

	public function index()
	{
		$this->render('quick_actions/index', array(
			'page_title' => 'Quick Actions',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'actions' => $this->actions,
		));
	}

	public function run()
	{
		$this->require_post();
		$key = $this->input->post('action_key', TRUE);
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);

		if ( ! isset($this->actions[$key]) || ! $config)
		{
			$this->session->set_flashdata('error', 'Action atau SSH config tidak valid.');
			redirect('quick-actions');
		}

		$action = $this->actions[$key];
		if ( ! has_role($action['roles']))
		{
			show_error('Anda tidak memiliki akses menjalankan action ini.', 403, 'Akses Ditolak');
		}

		$result = $this->remote_ssh->execute($config, $action['command'], 120);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Remote_history_model->service($config->server_id, $config->id, $this->current_user['id'], $action['label'], 'quick_action', $status, $result['output']);
		$this->Activity_model->log($this->current_user['id'], $action['label'], $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('quick-actions');
	}

	protected function action_map()
	{
		$ops = array('Super Admin', 'Admin', 'Operator');
		$admin = array('Super Admin', 'Admin');
		$super = array('Super Admin');

		return array(
			'restart_nginx' => array('label' => 'Restart Nginx', 'icon' => 'fas fa-sync', 'command' => 'sudo systemctl restart nginx && sudo systemctl status nginx --no-pager -l', 'roles' => $ops),
			'restart_apache' => array('label' => 'Restart Apache', 'icon' => 'fas fa-sync', 'command' => 'sudo systemctl restart apache2 && sudo systemctl status apache2 --no-pager -l', 'roles' => $ops),
			'restart_php_fpm' => array('label' => 'Restart PHP-FPM', 'icon' => 'fas fa-sync', 'command' => 'sudo systemctl restart php-fpm || sudo systemctl restart php8.2-fpm', 'roles' => $ops),
			'restart_mysql' => array('label' => 'Restart MySQL', 'icon' => 'fas fa-database', 'command' => 'sudo systemctl restart mysql && sudo systemctl status mysql --no-pager -l', 'roles' => $ops),
			'restart_mariadb' => array('label' => 'Restart MariaDB', 'icon' => 'fas fa-database', 'command' => 'sudo systemctl restart mariadb && sudo systemctl status mariadb --no-pager -l', 'roles' => $ops),
			'restart_docker' => array('label' => 'Restart Docker', 'icon' => 'fab fa-docker', 'command' => 'sudo systemctl restart docker && sudo systemctl status docker --no-pager -l', 'roles' => $ops),
			'restart_ssh' => array('label' => 'Restart SSH', 'icon' => 'fas fa-key', 'command' => 'sudo systemctl restart ssh || sudo systemctl restart sshd', 'roles' => $admin),
			'reload_nginx' => array('label' => 'Reload Nginx', 'icon' => 'fas fa-redo', 'command' => 'sudo systemctl reload nginx && sudo nginx -t', 'roles' => $ops),
			'clear_cache' => array('label' => 'Clear Cache', 'icon' => 'fas fa-broom', 'command' => 'sync; echo 3 | sudo tee /proc/sys/vm/drop_caches', 'roles' => $admin),
			'check_update' => array('label' => 'Check Update', 'icon' => 'fas fa-search', 'command' => 'sudo apt-get update -s', 'roles' => $ops),
			'update_package' => array('label' => 'Update Package', 'icon' => 'fas fa-download', 'command' => 'sudo apt-get update', 'roles' => $admin),
			'upgrade_package' => array('label' => 'Upgrade Package', 'icon' => 'fas fa-arrow-up', 'command' => 'sudo DEBIAN_FRONTEND=noninteractive apt-get -y upgrade', 'roles' => $admin),
			'reboot_server' => array('label' => 'Reboot Server', 'icon' => 'fas fa-power-off', 'command' => 'sudo reboot', 'roles' => $super),
			'shutdown_server' => array('label' => 'Shutdown Server', 'icon' => 'fas fa-plug', 'command' => 'sudo shutdown -h now', 'roles' => $super),
		);
	}
}
