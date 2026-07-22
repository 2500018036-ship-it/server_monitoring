<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
	}

	public function index()
	{
		$this->render('settings/index', array(
			'page_title' => 'Settings',
			'setting' => $this->Setting_model->get_settings(),
		));
	}

	public function update()
	{
		$this->require_post();
		$this->form_validation->set_rules('app_name', 'Nama Aplikasi', 'required|trim');
		$this->form_validation->set_rules('timezone', 'Timezone', 'required|trim');
		$this->form_validation->set_rules('monitoring_interval', 'Monitoring Interval', 'required|integer|greater_than[0]|less_than_equal_to[60]');
		$this->form_validation->set_rules('process_retention_days', 'Process Retention', 'required|integer|greater_than[0]|less_than_equal_to[365]');
		$this->form_validation->set_rules('log_retention_days', 'Log Retention', 'required|integer|greater_than[0]|less_than_equal_to[365]');
		$this->form_validation->set_rules('agent_api_key', 'Agent API Key', 'required|trim|min_length[20]');
		$this->form_validation->set_rules('api_rate_limit_per_minute', 'API Rate Limit', 'required|integer|greater_than[0]');

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('settings');
		}

		$setting = $this->Setting_model->get_settings();
		$logo = $this->upload_file('logo', 'assets/uploads/settings/', 'jpg|jpeg|png|gif|webp|svg', 2048);
		$favicon = $this->upload_file('favicon', 'assets/uploads/settings/', 'ico|png|jpg|jpeg', 1024);

		if ($logo === FALSE || $favicon === FALSE)
		{
			redirect('settings');
		}

		$smtp_port = $this->input->post('smtp_port', TRUE);

		$data = array(
			'app_name' => $this->input->post('app_name', TRUE),
			'timezone' => $this->input->post('timezone', TRUE),
			'openai_api_key' => $this->input->post('openai_api_key', TRUE),
			'gemini_api_key' => $this->input->post('gemini_api_key', TRUE),
			'ollama_url' => $this->input->post('ollama_url', TRUE),
			'smtp_host' => $this->input->post('smtp_host', TRUE),
			'smtp_port' => $smtp_port === '' ? NULL : $smtp_port,
			'smtp_user' => $this->input->post('smtp_user', TRUE),
			'smtp_password' => $this->input->post('smtp_password', TRUE),
			'monitoring_interval' => $this->input->post('monitoring_interval', TRUE),
			'process_retention_days' => $this->input->post('process_retention_days', TRUE),
			'log_retention_days' => $this->input->post('log_retention_days', TRUE),
			'agent_api_key' => $this->input->post('agent_api_key', TRUE),
			'api_allowed_origins' => $this->input->post('api_allowed_origins', TRUE),
			'api_rate_limit_per_minute' => $this->input->post('api_rate_limit_per_minute', TRUE),
		);

		if ($logo)
		{
			$data['logo'] = 'assets/uploads/settings/'.$logo;
		}

		if ($favicon)
		{
			$data['favicon'] = 'assets/uploads/settings/'.$favicon;
		}

		$this->Setting_model->update_settings($setting->id, $data);
		$this->session->set_flashdata('success', 'Settings berhasil disimpan.');

		redirect('settings');
	}
}
