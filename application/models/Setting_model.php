<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Setting_model extends CI_Model
{
	protected $table = 'settings';

	public function get_settings()
	{
		$setting = $this->db
			->order_by('id', 'ASC')
			->limit(1)
			->get($this->table)
			->row();

		if ($setting)
		{
			return $setting;
		}

		$defaults = array(
			'app_name' => 'Server Monitoring',
			'logo' => NULL,
			'favicon' => NULL,
			'timezone' => 'Asia/Jakarta',
			'monitoring_interval' => 3,
			'agent_api_key' => 'sm_agent_00c3ed8d3079d40bcc7395a79e864c50842932b5b39a8b7f',
			'api_rate_limit_per_minute' => 1000,
			'created_at' => date('Y-m-d H:i:s'),
		);

		$this->db->insert($this->table, $defaults);

		return $this->db
			->where('id', $this->db->insert_id())
			->get($this->table)
			->row();
	}

	public function update_settings($id, $data)
	{
		return $this->db
			->where('id', $id)
			->update($this->table, $data);
	}
}
