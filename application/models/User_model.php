<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
	protected $table = 'users';

	public function get_all()
	{
		return $this->db
			->select('users.*, roles.role_name')
			->from($this->table)
			->join('roles', 'roles.id = users.role_id', 'left')
			->order_by('users.fullname', 'ASC')
			->get()
			->result();
	}

	public function count_all()
	{
		return $this->db->count_all($this->table);
	}

	public function find($id)
	{
		return $this->db
			->select('users.*, roles.role_name')
			->from($this->table)
			->join('roles', 'roles.id = users.role_id', 'left')
			->where('users.id', $id)
			->get()
			->row();
	}

	public function find_by_identity($identity)
	{
		return $this->db
			->select('users.*, roles.role_name')
			->from($this->table)
			->join('roles', 'roles.id = users.role_id', 'left')
			->group_start()
				->where('users.username', $identity)
				->or_where('users.email', $identity)
			->group_end()
			->get()
			->row();
	}

	public function find_by_remember_token($token_hash)
	{
		return $this->db
			->select('users.*, roles.role_name')
			->from($this->table)
			->join('roles', 'roles.id = users.role_id', 'left')
			->where('users.remember_token', $token_hash)
			->where('users.status', 'active')
			->get()
			->row();
	}

	public function create($data)
	{
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = date('Y-m-d H:i:s');

		$this->db->insert($this->table, $data);

		return $this->db->insert_id();
	}

	public function update($id, $data)
	{
		$data['updated_at'] = date('Y-m-d H:i:s');

		return $this->db
			->where('id', $id)
			->update($this->table, $data);
	}

	public function delete($id)
	{
		return $this->db
			->where('id', $id)
			->delete($this->table);
	}

	public function update_login($id, $ip_address)
	{
		return $this->update($id, array(
			'last_login' => date('Y-m-d H:i:s'),
			'last_login_ip' => $ip_address,
		));
	}

	public function update_remember_token($id, $token_hash)
	{
		return $this->update($id, array('remember_token' => $token_hash));
	}

	public function change_password($id, $password_hash)
	{
		return $this->update($id, array('password' => $password_hash));
	}

	public function set_status($id, $status)
	{
		return $this->update($id, array('status' => $status));
	}

	public function username_exists($username, $ignore_id = NULL)
	{
		$this->db->where('username', $username);

		if ($ignore_id)
		{
			$this->db->where('id !=', $ignore_id);
		}

		return $this->db->count_all_results($this->table) > 0;
	}

	public function email_exists($email, $ignore_id = NULL)
	{
		$this->db->where('email', $email);

		if ($ignore_id)
		{
			$this->db->where('id !=', $ignore_id);
		}

		return $this->db->count_all_results($this->table) > 0;
	}
}
