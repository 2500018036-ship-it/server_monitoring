<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller
{
	const REMEMBER_COOKIE = 'server_monitoring_remember';
	const MAX_ATTEMPTS = 5;
	const LOCK_SECONDS = 900;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('User_model');
		$this->load->model('Setting_model');
		$this->load->model('Activity_model');
	}

	public function index()
	{
		if ($this->session->userdata('user'))
		{
			redirect('dashboard');
		}

		if ($this->login_from_remember_cookie())
		{
			redirect('dashboard');
		}

		if ($this->input->method(TRUE) === 'POST')
		{
			$this->handle_login();
		}

		$this->load->view('auth/login', array(
			'app_setting' => $this->Setting_model->get_settings(),
			'page_title' => 'Login',
		));
	}

	public function logout()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('login');
		}

		$user = $this->session->userdata('user');

		if (is_array($user) && ! empty($user['id']))
		{
			$this->Activity_model->log($user['id'], 'Logout', $this->input->ip_address());
			$this->User_model->update_remember_token($user['id'], NULL);
		}

		delete_cookie(self::REMEMBER_COOKIE);
		$this->session->unset_userdata(array('user', 'user_logged_in'));
		$this->session->set_flashdata('success', 'Anda berhasil logout.');

		redirect('login');
	}

	public function forgot_password()
	{
		$this->load->view('auth/password_placeholder', array(
			'app_setting' => $this->Setting_model->get_settings(),
			'page_title' => 'Forgot Password',
			'title' => 'Forgot Password',
			'message' => 'Halaman ini disiapkan sebagai placeholder untuk phase berikutnya.',
		));
	}

	public function reset_password()
	{
		$this->load->view('auth/password_placeholder', array(
			'app_setting' => $this->Setting_model->get_settings(),
			'page_title' => 'Reset Password',
			'title' => 'Reset Password',
			'message' => 'Alur reset password akan diimplementasikan pada phase lanjutan.',
		));
	}

	protected function handle_login()
	{
		if ($this->is_login_locked())
		{
			$this->session->set_flashdata('error', 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.');
			redirect('login');
		}

		$this->form_validation->set_rules('identity', 'Username / Email', 'required|trim');
		$this->form_validation->set_rules('password', 'Password', 'required|trim');

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('login');
		}

		$identity = $this->input->post('identity', TRUE);
		$password = $this->input->post('password', FALSE);
		$user = $this->User_model->find_by_identity($identity);

		if ( ! $user || $user->status !== 'active' || ! password_verify($password, $user->password))
		{
			$this->register_failed_attempt();
			$this->session->set_flashdata('error', 'Username/email atau password tidak sesuai.');
			redirect('login');
		}

		$this->clear_login_attempts();
		$this->start_user_session($user);
		$this->User_model->update_login($user->id, $this->input->ip_address());
		$this->Activity_model->log($user->id, 'Login', $this->input->ip_address());

		if ($this->input->post('remember'))
		{
			$token = $this->generate_token();
			$this->User_model->update_remember_token($user->id, hash('sha256', $token));
			set_cookie(self::REMEMBER_COOKIE, $token, 2592000, '', '/', '', FALSE, TRUE);
		}
		else
		{
			$this->User_model->update_remember_token($user->id, NULL);
			delete_cookie(self::REMEMBER_COOKIE);
		}

		redirect('dashboard');
	}

	protected function start_user_session($user)
	{
		$this->session->set_userdata('user_logged_in', TRUE);
		$this->session->set_userdata('user', array(
			'id' => $user->id,
			'fullname' => $user->fullname,
			'username' => $user->username,
			'email' => $user->email,
			'role_id' => $user->role_id,
			'role_name' => $user->role_name,
			'photo' => $user->photo,
		));
	}

	protected function login_from_remember_cookie()
	{
		$token = get_cookie(self::REMEMBER_COOKIE, TRUE);

		if ( ! $token)
		{
			return FALSE;
		}

		$user = $this->User_model->find_by_remember_token(hash('sha256', $token));

		if ( ! $user)
		{
			delete_cookie(self::REMEMBER_COOKIE);

			return FALSE;
		}

		$this->start_user_session($user);
		$this->User_model->update_login($user->id, $this->input->ip_address());
		$this->Activity_model->log($user->id, 'Login dengan Remember Me', $this->input->ip_address());

		return TRUE;
	}

	protected function is_login_locked()
	{
		$blocked_until = (int) $this->session->userdata('login_blocked_until');

		if ($blocked_until > time())
		{
			return TRUE;
		}

		if ($blocked_until > 0)
		{
			$this->clear_login_attempts();
		}

		return FALSE;
	}

	protected function register_failed_attempt()
	{
		$attempts = (int) $this->session->userdata('login_attempts');
		$attempts++;

		$this->session->set_userdata('login_attempts', $attempts);

		if ($attempts >= self::MAX_ATTEMPTS)
		{
			$this->session->set_userdata('login_blocked_until', time() + self::LOCK_SECONDS);
		}
	}

	protected function clear_login_attempts()
	{
		$this->session->unset_userdata(array('login_attempts', 'login_blocked_until'));
	}

	protected function generate_token()
	{
		if (function_exists('random_bytes'))
		{
			return bin2hex(random_bytes(32));
		}

		return bin2hex(openssl_random_pseudo_bytes(32));
	}
}
