<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role_model extends CI_Model
{
	protected $table = 'roles';

	public function get_all()
	{
		return $this->db
			->order_by('id', 'ASC')
			->get($this->table)
			->result();
	}

	public function find($id)
	{
		return $this->db
			->where('id', $id)
			->get($this->table)
			->row();
	}
}
