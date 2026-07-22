<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Server_model');
		$this->load->model('User_model');
		$this->load->model('Monitoring_model');
	}

	public function index()
	{
		$selected_server_id = (int) $this->input->get('server_id', TRUE);
		$monitoring = $this->Monitoring_model->dashboard_payload($selected_server_id);

		$this->render('dashboard/index', array(
			'page_title' => 'Dashboard',
			'total_servers' => $this->Server_model->count_all(),
			'total_users' => $this->User_model->count_all(),
			'monitoring' => $monitoring,
			'poll_interval' => max((int) $this->Setting_model->get_settings()->monitoring_interval, 1),
		));
	}
}
