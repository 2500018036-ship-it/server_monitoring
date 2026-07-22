<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity_model extends CI_Model
{
	protected $table = 'activity_logs';

	public function log($user_id, $activity, $ip_address, $server_id = NULL, $status = 'success', $details = NULL)
	{
		if (empty($user_id) || empty($activity))
		{
			return FALSE;
		}

		$data = array(
			'user_id' => $user_id,
			'activity' => $activity,
			'ip_address' => $ip_address,
			'created_at' => date('Y-m-d H:i:s'),
		);

		if ($this->db->field_exists('server_id', $this->table))
		{
			$data['server_id'] = $server_id;
			$data['action_status'] = in_array($status, array('success', 'failed', 'pending'), TRUE) ? $status : 'success';
			$data['details'] = $details;
		}

		return $this->db->insert($this->table, $data);
	}

	public function get_recent($limit = 100)
	{
		$this->db
			->select('activity_logs.*, users.fullname, users.username');

		if ($this->db->field_exists('server_id', $this->table))
		{
			$this->db->select('servers.server_name');
		}

		$this->db
			->from($this->table)
			->join('users', 'users.id = activity_logs.user_id', 'left');

		if ($this->db->field_exists('server_id', $this->table))
		{
			$this->db->join('servers', 'servers.id = activity_logs.server_id', 'left');
		}

		return $this->db
			->order_by('activity_logs.created_at', 'DESC')
			->limit($limit)
			->get()
			->result();
	}
}
