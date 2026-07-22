<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	protected $current_user = array();
	protected $allowed_roles = array();

	public function __construct($allowed_roles = array())
	{
		parent::__construct();

		$this->allowed_roles = $allowed_roles;
		$this->load->model('User_model');
		$this->load->model('Setting_model');
		$this->load->model('Activity_model');

		$this->guard_authenticated_user();
		$this->guard_role_access();
	}

	protected function guard_authenticated_user()
	{
		$session_user = $this->session->userdata('user');

		if ( ! is_array($session_user) || empty($session_user['id']))
		{
			redirect('login');
		}

		$user = $this->User_model->find($session_user['id']);

		if ( ! $user || $user->status !== 'active')
		{
			$this->session->unset_userdata(array('user', 'user_logged_in'));
			$this->session->set_flashdata('error', 'Sesi tidak valid atau akun sudah dinonaktifkan.');
			redirect('login');
		}

		$this->current_user = array(
			'id' => $user->id,
			'fullname' => $user->fullname,
			'username' => $user->username,
			'email' => $user->email,
			'role_id' => $user->role_id,
			'role_name' => $user->role_name,
			'photo' => $user->photo,
		);

		$this->session->set_userdata('user', $this->current_user);
	}

	protected function guard_role_access()
	{
		if ( ! empty($this->allowed_roles) && ! has_role($this->allowed_roles))
		{
			show_error('Anda tidak memiliki akses ke halaman ini.', 403, 'Akses Ditolak');
		}
	}

	protected function render($view, $data = array())
	{
		$setting = $this->Setting_model->get_settings();
		$selected_server_id = (int) $this->input->get('server_id', TRUE);

		if ( ! isset($data['page_title']))
		{
			$data['page_title'] = 'Server Monitoring';
		}

		if (isset($data['monitoring']))
		{
			$data['navbar_monitoring'] = $data['monitoring'];
		}
		else
		{
			$this->load->model('Monitoring_model');
			$data['navbar_monitoring'] = $this->Monitoring_model->dashboard_payload($selected_server_id);
		}

		$data['app_setting'] = $setting;
		$data['current_user'] = $this->current_user;
		$data['content_view'] = $view;

		$this->load->view('layouts/header', $data);
		$this->load->view('layouts/navbar', $data);
		$this->load->view('layouts/sidebar', $data);
		$this->load->view($view, $data);
		$this->load->view('layouts/footer', $data);
	}

	protected function record_activity($activity)
	{
		$this->Activity_model->log(
			$this->current_user['id'],
			$activity,
			$this->input->ip_address()
		);
	}

	protected function require_post()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_error('Method not allowed.', 405, 'Method Not Allowed');
		}
	}

	protected function upload_file($field, $path, $allowed_types = 'jpg|jpeg|png|gif|webp|ico', $max_size = 2048)
	{
		if (empty($_FILES[$field]['name']))
		{
			return NULL;
		}

		$config = array(
			'upload_path' => FCPATH.$path,
			'allowed_types' => $allowed_types,
			'max_size' => $max_size,
			'encrypt_name' => TRUE,
		);

		$this->load->library('upload');
		$this->upload->initialize($config);

		if ( ! $this->upload->do_upload($field))
		{
			$this->session->set_flashdata('error', strip_tags($this->upload->display_errors()));

			return FALSE;
		}

		$file = $this->upload->data();

		return $file['file_name'];
	}
}
