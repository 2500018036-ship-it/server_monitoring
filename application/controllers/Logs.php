<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Logs extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Monitoring_model');
	}

	public function index()
	{
		$server_id = (int) $this->input->get('server_id', TRUE);

		$this->render('logs/index', array(
			'page_title' => 'Logs',
			'monitoring' => $this->Monitoring_model->dashboard_payload($server_id),
			'poll_interval' => min((int) $this->Setting_model->get_settings()->monitoring_interval, 3),
		));
	}

	public function activity()
	{
		$this->render('logs/activity', array(
			'page_title' => 'Activity Logs',
			'logs' => $this->Activity_model->get_recent(200),
		));
	}
}
