<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AI extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin', 'Operator'));
	}

	public function index()
	{
		$this->render('pages/placeholder', array(
			'page_title' => 'AI Assistant',
			'icon' => 'fas fa-robot',
			'description' => 'Halaman AI Assistant hanya placeholder. API key disimpan di Settings untuk kebutuhan phase lanjutan.',
		));
	}
}
