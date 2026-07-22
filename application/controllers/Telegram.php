<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
	}

	public function index()
	{
		$this->render('pages/placeholder', array(
			'page_title' => 'Telegram',
			'icon' => 'fab fa-telegram-plane',
			'description' => 'Konfigurasi Telegram tersedia di Settings. Integrasi bot belum dijalankan pada phase 1.',
		));
	}
}
