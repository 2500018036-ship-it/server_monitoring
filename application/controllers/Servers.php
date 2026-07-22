<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Servers extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Monitoring_model');
	}

	public function index()
	{
		$this->render('servers/index', array(
			'page_title' => 'Servers',
			'servers' => $this->Monitoring_model->get_servers(),
		));
	}
}
