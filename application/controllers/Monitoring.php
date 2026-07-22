<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Monitoring extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Monitoring_model');
	}

	public function index()
	{
		$server_id = (int) $this->input->get('server_id', TRUE);

		$this->render('monitoring/index', array(
			'page_title' => 'Monitoring',
			'monitoring' => $this->Monitoring_model->dashboard_payload($server_id),
			'poll_interval' => max((int) $this->Setting_model->get_settings()->monitoring_interval, 1),
		));
	}
}
