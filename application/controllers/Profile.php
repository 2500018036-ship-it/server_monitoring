<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends MY_Controller
{
	public function index()
	{
		$this->render('profile/index', array(
			'page_title' => 'Profile',
			'user' => $this->User_model->find($this->current_user['id']),
		));
	}

	public function update()
	{
		$this->require_post();
		$user = $this->User_model->find($this->current_user['id']);

		$this->form_validation->set_rules('fullname', 'Fullname', 'required|trim|min_length[3]');
		$this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email');

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('profile');
		}

		$email = $this->input->post('email', TRUE);

		if ($this->User_model->email_exists($email, $this->current_user['id']))
		{
			$this->session->set_flashdata('error', 'Email sudah digunakan.');
			redirect('profile');
		}

		$photo = $this->upload_file('photo', 'assets/uploads/users/');

		if ($photo === FALSE)
		{
			redirect('profile');
		}

		$data = array(
			'fullname' => $this->input->post('fullname', TRUE),
			'email' => $email,
		);

		if ($photo)
		{
			$data['photo'] = $photo;
		}

		$this->User_model->update($user->id, $data);
		$this->record_activity('Edit Profile');
		$this->session->set_flashdata('success', 'Profile berhasil diperbarui.');

		redirect('profile');
	}

	public function change_password()
	{
		$this->require_post();
		$user = $this->User_model->find($this->current_user['id']);

		$this->form_validation->set_rules('current_password', 'Password Saat Ini', 'required|trim');
		$this->form_validation->set_rules('new_password', 'Password Baru', 'required|trim|min_length[6]');
		$this->form_validation->set_rules('confirm_password', 'Konfirmasi Password', 'required|trim|matches[new_password]');

		if ($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('error', validation_errors('', '<br>'));
			redirect('profile');
		}

		if ( ! password_verify($this->input->post('current_password', FALSE), $user->password))
		{
			$this->session->set_flashdata('error', 'Password saat ini tidak sesuai.');
			redirect('profile');
		}

		$this->User_model->change_password($user->id, password_hash($this->input->post('new_password', FALSE), PASSWORD_DEFAULT));
		$this->record_activity('Ganti Password');
		$this->session->set_flashdata('success', 'Password berhasil diganti.');

		redirect('profile');
	}
}
