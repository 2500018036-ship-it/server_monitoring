<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Backup_manager extends MY_Controller
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
		$this->render('backup_manager/index', array(
			'page_title' => 'Backup Manager',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'history' => $this->Remote_history_model->recent('backup_history', 100),
		));
	}

	public function run()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$type = $this->input->post('backup_type', TRUE);
		$source = $this->input->post('source_path', TRUE);
		$allowed = array('website', 'database', 'configuration', 'docker_volume');

		if ( ! $config || ! in_array($type, $allowed, TRUE) || $source === '')
		{
			$this->session->set_flashdata('error', 'Backup request tidak valid.');
			redirect('backup-manager');
		}

		$target = '/tmp/'.$type.'-backup-'.date('Ymd-His').'.tar.gz';
		$command = $type === 'database'
			? 'mysqldump '.remote_arg($source).' > '.remote_arg(str_replace('.tar.gz', '.sql', $target)).' && gzip -f '.remote_arg(str_replace('.tar.gz', '.sql', $target))
			: 'sudo tar -czf '.remote_arg($target).' '.remote_arg($source).' && ls -lh '.remote_arg($target);
		$result = $this->remote_ssh->execute($config, $command, 300);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Remote_history_model->backup($config->server_id, $config->id, $this->current_user['id'], $type, 'backup', $target, $status, $result['output']);
		$this->Activity_model->log($this->current_user['id'], 'Backup '.$type, $this->input->ip_address(), $config->server_id, $status, $result['output']);
		$this->notify_backup_event($config->server_id, (bool) $result['ok'], $result['output'], basename($target), $target);
		$this->session->set_flashdata($result['ok'] ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($result['output']).'</pre>');

		redirect('backup-manager');
	}

	protected function notify_backup_event($server_id, $success, $message, $file_name, $event_id)
	{
		try
		{
			$this->load->library('Telegram_notifier');
			$this->telegram_notifier->backup($server_id, $success, $message, $file_name, $event_id);
		}
		catch (Exception $e)
		{
			log_message('error', 'Telegram backup manager notification failed: '.$e->getMessage());
		}
	}
}
