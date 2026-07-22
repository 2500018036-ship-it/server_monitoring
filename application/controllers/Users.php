<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin'));
		$this->load->model('Role_model');
	}

	public function index()
	{
		$this->render('users/index', array(
			'page_title' => 'Users',
			'users' => $this->User_model->get_all(),
			'roles' => $this->Role_model->get_all(),
		));
	}

	public function store()
	{
		$this->require_post();
		$this->validate_user_form(TRUE);

		if ($this->form_validation->run() === FALSE)
		{
			$this->flash_validation_error('users');
		}

		$photo = $this->upload_file('photo', 'assets/uploads/users/');

		if ($photo === FALSE)
		{
			redirect('users');
		}

		$data = array(
			'fullname' => $this->input->post('fullname', TRUE),
			'username' => $this->input->post('username', TRUE),
			'email' => $this->input->post('email', TRUE),
			'password' => password_hash($this->input->post('password', FALSE), PASSWORD_DEFAULT),
			'role_id' => $this->input->post('role_id', TRUE),
			'photo' => $photo,
			'status' => $this->input->post('status', TRUE),
		);

		$this->User_model->create($data);
		$this->record_activity('Tambah User: '.$data['username']);
		$this->session->set_flashdata('success', 'User berhasil ditambahkan.');

		redirect('users');
	}

	public function update($id)
	{
		$this->require_post();
		$id = (int) $id;
		$user = $this->User_model->find($id);

		if ( ! $user)
		{
			show_404();
		}

		$this->validate_user_form(FALSE);

		if ($this->form_validation->run() === FALSE)
		{
			$this->flash_validation_error('users');
		}

		$username = $this->input->post('username', TRUE);
		$email = $this->input->post('email', TRUE);

		if ($this->User_model->username_exists($username, $id))
		{
			$this->session->set_flashdata('error', 'Username sudah digunakan.');
			redirect('users');
		}

		if ($this->User_model->email_exists($email, $id))
		{
			$this->session->set_flashdata('error', 'Email sudah digunakan.');
			redirect('users');
		}

		$photo = $this->upload_file('photo', 'assets/uploads/users/');

		if ($photo === FALSE)
		{
			redirect('users');
		}

		$data = array(
			'fullname' => $this->input->post('fullname', TRUE),
			'username' => $username,
			'email' => $email,
			'role_id' => $this->input->post('role_id', TRUE),
			'status' => $this->input->post('status', TRUE),
		);

		if ($photo)
		{
			$data['photo'] = $photo;
		}

		if ($this->input->post('password'))
		{
			$data['password'] = password_hash($this->input->post('password', FALSE), PASSWORD_DEFAULT);
		}

		if ($id === (int) $this->current_user['id'])
		{
			$data['role_id'] = $user->role_id;
			$data['status'] = 'active';
		}

		$this->User_model->update($id, $data);
		$this->record_activity('Edit User: '.$username);
		$this->session->set_flashdata('success', 'User berhasil diperbarui.');

		redirect('users');
	}

	public function toggle($id)
	{
		$this->require_post();
		$id = (int) $id;
		$user = $this->User_model->find($id);

		if ( ! $user)
		{
			show_404();
		}

		if ($id === (int) $this->current_user['id'])
		{
			$this->session->set_flashdata('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
			redirect('users');
		}

		$status = $user->status === 'active' ? 'inactive' : 'active';
		$this->User_model->set_status($id, $status);
		$this->record_activity('Ubah Status User: '.$user->username.' menjadi '.$status);
		$this->session->set_flashdata('success', 'Status user berhasil diperbarui.');

		redirect('users');
	}

	public function delete($id)
	{
		$this->require_post();
		$id = (int) $id;
		$user = $this->User_model->find($id);

		if ( ! $user)
		{
			show_404();
		}

		if ($id === (int) $this->current_user['id'])
		{
			$this->session->set_flashdata('error', 'Anda tidak dapat menghapus akun sendiri.');
			redirect('users');
		}

		$this->User_model->delete($id);
		$this->record_activity('Hapus User: '.$user->username);
		$this->session->set_flashdata('success', 'User berhasil dihapus.');

		redirect('users');
	}

	protected function validate_user_form($is_create)
	{
		$this->form_validation->set_rules('fullname', 'Fullname', 'required|trim|min_length[3]');
		$this->form_validation->set_rules('username', 'Username', 'required|trim|min_length[3]'.($is_create ? '|is_unique[users.username]' : ''));
		$this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email'.($is_create ? '|is_unique[users.email]' : ''));
		$this->form_validation->set_rules('role_id', 'Role', 'required|integer');
		$this->form_validation->set_rules('status', 'Status', 'required|in_list[active,inactive]');

		if ($is_create)
		{
			$this->form_validation->set_rules('password', 'Password', 'required|trim|min_length[6]');
		}
		else
		{
			$this->form_validation->set_rules('password', 'Password', 'trim|min_length[6]');
		}
	}

	protected function flash_validation_error($redirect_to)
	{
		$this->session->set_flashdata('error', validation_errors('', '<br>'));
		redirect($redirect_to);
	}
}
