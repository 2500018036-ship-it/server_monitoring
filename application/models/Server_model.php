<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Server_model extends CI_Model
{
	protected $table = 'servers';

	public function get_all()
	{
		return $this->db
			->order_by('server_name', 'ASC')
			->get($this->table)
			->result();
	}

	public function count_all()
	{
		return $this->db->count_all($this->table);
	}
}
