<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File_manager extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->library('Remote_ssh');
		$this->load->helper('download');
	}

	public function index()
	{
		$this->render('file_manager/index', array(
			'page_title' => 'File Manager',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'phpseclib_available' => $this->remote_ssh->available(),
		));
	}

	public function browse()
	{
		$this->require_post();
		$config = $this->config_from_post();
		$path = $this->input->post('path', TRUE);
		$result = $config ? $this->remote_ssh->list_dir($config, $path) : array('ok' => FALSE, 'message' => 'SSH config tidak valid.', 'items' => array());
		$this->json_response($result);
	}

	public function preview()
	{
		$this->require_post();
		$config = $this->config_from_post();
		$path = $this->input->post('path', TRUE);
		$result = $config ? $this->remote_ssh->read_file($config, $path) : array('ok' => FALSE, 'message' => 'SSH config tidak valid.', 'content' => '');
		$this->json_response($result);
	}

	public function save()
	{
		$this->require_post();
		$config = $this->config_from_post();
		$path = $this->input->post('path', TRUE);
		$content = $this->input->post('content', FALSE);
		$result = $config ? $this->remote_ssh->write_file($config, $path, $content) : array('ok' => FALSE, 'message' => 'SSH config tidak valid.');
		$this->log_file_action($config, 'save', $path, NULL, $result);
		$this->json_response($result);
	}

	public function upload()
	{
		$this->require_post();
		$config = $this->config_from_post();
		$path = rtrim($this->input->post('path', TRUE), '/');

		if ( ! $config || empty($_FILES['file']['tmp_name']))
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Upload tidak valid.'));
			return;
		}

		$remote = $path.'/'.basename($_FILES['file']['name']);
		$result = $this->remote_ssh->upload($config, $remote, $_FILES['file']['tmp_name']);
		$this->log_file_action($config, 'upload', $_FILES['file']['name'], $remote, $result);
		$this->json_response($result);
	}

	public function download()
	{
		$config = $this->Ssh_config_model->find((int) $this->input->get('ssh_config_id', TRUE), TRUE);
		$path = $this->input->get('path', TRUE);

		if ( ! $config)
		{
			show_404();
		}

		$result = $this->remote_ssh->read_file($config, $path);

		if ( ! $result['ok'])
		{
			show_error($result['message'], 500);
		}

		$this->Remote_history_model->file($config->server_id, $config->id, $this->current_user['id'], 'download', $path, NULL, 'success', 'Downloaded');
		force_download(basename($path), $result['content']);
	}

	public function action()
	{
		$this->require_post();
		$config = $this->config_from_post();
		$action = $this->input->post('file_action', TRUE);
		$source = $this->input->post('source_path', TRUE);
		$target = $this->input->post('target_path', TRUE);

		if ( ! $config)
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'SSH config tidak valid.'));
			return;
		}

		switch ($action)
		{
			case 'mkdir':
				$result = $this->remote_ssh->mkdir($config, $target);
				break;
			case 'rename':
			case 'move':
				$result = $this->remote_ssh->rename($config, $source, $target);
				break;
			case 'delete':
				$result = $this->remote_ssh->delete_path($config, $source);
				break;
			case 'copy':
				$result = $this->remote_ssh->execute($config, 'cp -a '.remote_arg($source).' '.remote_arg($target), 60);
				$result['message'] = $result['ok'] ? 'Copied.' : $result['output'];
				break;
			default:
				$result = array('ok' => FALSE, 'message' => 'Action tidak valid.');
		}

		$this->log_file_action($config, $action, $source, $target, $result);
		$this->json_response($result);
	}

	public function search()
	{
		$this->require_post();
		$config = $this->config_from_post();
		$path = $this->input->post('path', TRUE) ?: '/';
		$query = $this->input->post('query', TRUE);

		if ( ! $config || $query === '')
		{
			$this->json_response(array('ok' => FALSE, 'message' => 'Search tidak valid.', 'output' => ''));
			return;
		}

		$result = $this->remote_ssh->execute($config, 'find '.remote_arg($path).' -maxdepth 5 -iname '.remote_arg('*'.$query.'*').' 2>/dev/null | head -100', 30);
		$this->json_response(array('ok' => $result['ok'], 'message' => $result['ok'] ? 'OK' : $result['output'], 'output' => $result['output']));
	}

	protected function config_from_post()
	{
		return $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
	}

	protected function log_file_action($config, $action, $source, $target, $result)
	{
		if ( ! $config)
		{
			return;
		}

		$status = ! empty($result['ok']) ? 'success' : 'failed';
		$message = isset($result['message']) ? $result['message'] : (isset($result['output']) ? $result['output'] : '');
		$this->Remote_history_model->file($config->server_id, $config->id, $this->current_user['id'], $action, $source, $target, $status, $message);
		$this->Activity_model->log($this->current_user['id'], ucfirst($action).' File: '.$source, $this->input->ip_address(), $config->server_id, $status, $message);
	}

	protected function json_response($payload)
	{
		$payload['csrf_hash'] = $this->security->get_csrf_hash();
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($payload));
	}
}
