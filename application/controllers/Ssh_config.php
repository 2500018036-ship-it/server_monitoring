<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ssh_config extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Ssh_config_model');
		$this->load->model('Server_model');
		$this->load->model('Monitoring_model');
		$this->load->library('Remote_ssh');
	}

	public function index()
	{
		$servers = $this->Monitoring_model->get_servers();

		$this->render('ssh_config/index', array(
			'page_title' => 'SSH Config',
			'configs' => $this->Ssh_config_model->get_all(),
			'servers' => $servers,
			'phpseclib_available' => $this->remote_ssh->available(),
		));
	}

	public function store()
	{
		$this->require_post();
		$this->validate_form(TRUE);

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('ssh-config');
		}

		$data = $this->input_data(TRUE);
		$id = $this->Ssh_config_model->create($data);
		$this->record_activity('Tambah SSH Config: '.$data['name']);
		$this->session->set_flashdata('success', 'SSH configuration berhasil disimpan.');

		redirect('ssh-config');
	}

	public function update($id)
	{
		$this->require_post();
		$id = (int) $id;

		if ( ! $this->Ssh_config_model->find($id))
		{
			show_404();
		}

		$this->validate_form(FALSE);

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('ssh-config');
		}

		$data = $this->input_data(FALSE);
		$this->Ssh_config_model->update($id, $data);
		$this->record_activity('Edit SSH Config: '.$data['name']);
		$this->session->set_flashdata('success', 'SSH configuration berhasil diperbarui.');

		redirect('ssh-config');
	}

	public function delete($id)
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $id);

		if ( ! $config)
		{
			show_404();
		}

		$this->Ssh_config_model->delete($config->id);
		$this->record_activity('Delete SSH Config: '.$config->name);
		$this->session->set_flashdata('success', 'SSH configuration berhasil dihapus.');

		redirect('ssh-config');
	}

	public function test($id)
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $id, TRUE);

		if ( ! $config)
		{
			show_404();
		}

		$result = $this->remote_ssh->test($config);
		$status = $result['ok'] ? 'success' : 'failed';
		$this->Activity_model->log($this->current_user['id'], 'Test SSH Connection: '.$config->name, $this->input->ip_address(), $config->server_id, $status, $result['output']);

		if ($result['ok'])
		{
			$this->Ssh_config_model->touch_connected($config->id);
			$this->session->set_flashdata('success', 'Connection OK: '.html_escape($result['output']));
		}
		else
		{
			$this->session->set_flashdata('error', 'Connection gagal: '.html_escape($result['output']));
		}

		redirect('ssh-config');
	}

	protected function validate_form($is_create)
	{
		$this->form_validation->set_rules('name', 'Name', 'required|trim|min_length[3]');
		$this->form_validation->set_rules('host', 'Host', 'required|trim');
		$this->form_validation->set_rules('port', 'Port', 'required|integer|greater_than[0]|less_than[65536]');
		$this->form_validation->set_rules('username', 'Username', 'required|trim');
		$this->form_validation->set_rules('auth_type', 'Authentication Type', 'required|in_list[password,private_key]');
		$this->form_validation->set_rules('status', 'Status', 'required|in_list[active,inactive]');

		if ($is_create && $this->input->post('auth_type') === 'password')
		{
			$this->form_validation->set_rules('password', 'Password', 'required');
		}

		if ($is_create && $this->input->post('auth_type') === 'private_key')
		{
			$this->form_validation->set_rules('private_key', 'Private Key', 'required');
		}
	}

	protected function input_data($is_create)
	{
		return array(
			'server_id' => $this->input->post('server_id', TRUE),
			'name' => $this->input->post('name', TRUE),
			'host' => $this->input->post('host', TRUE),
			'port' => $this->input->post('port', TRUE),
			'username' => $this->input->post('username', TRUE),
			'auth_type' => $this->input->post('auth_type', TRUE),
			'password' => $this->input->post('password', FALSE),
			'private_key' => $this->input->post('private_key', FALSE),
			'passphrase' => $this->input->post('passphrase', FALSE),
			'status' => $this->input->post('status', TRUE),
			'created_by' => $this->current_user['id'],
		);
	}
}
