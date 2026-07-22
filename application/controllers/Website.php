<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Website extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Monitoring_model');
	}

	public function index()
	{
		$server_id = (int) $this->input->get('server_id', TRUE);
		$monitoring = $this->Monitoring_model->dashboard_payload($server_id);

		$this->render('website/index', array(
			'page_title' => 'Website',
			'monitoring' => $monitoring,
		));
	}
}
