<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Setting_model extends CI_Model
{
	protected $table = 'settings';

	protected function generate_api_key($prefix = 'sm_install_')
	{
		try
		{
			return strtolower($prefix.bin2hex(random_bytes(24)));
		}
		catch (Exception $e)
		{
			return strtolower($prefix.sha1(uniqid('', TRUE).microtime(TRUE)));
		}
	}

	public function get_settings()
	{
		$setting = $this->db
			->order_by('id', 'ASC')
			->limit(1)
			->get($this->table)
			->row();

		if ($setting)
		{
			return $this->ensure_runtime_defaults($setting);
		}

		$defaults = array(
			'app_name' => 'Server Monitoring',
			'logo' => NULL,
			'favicon' => NULL,
			'timezone' => 'Asia/Jakarta',
			'monitoring_interval' => 10,
			'process_retention_days' => 7,
			'log_retention_days' => 14,
			'agent_api_key' => $this->generate_api_key(),
			'api_rate_limit_per_minute' => 1000,
			'created_at' => date('Y-m-d H:i:s'),
		);

		$this->db->insert($this->table, $defaults);

		$setting = $this->db
			->where('id', $this->db->insert_id())
			->get($this->table)
			->row();

		return $this->ensure_runtime_defaults($setting);
	}

	public function update_settings($id, $data)
	{
		return $this->db
			->where('id', $id)
			->update($this->table, $data);
	}

	protected function ensure_runtime_defaults($setting)
	{
		if ( ! $setting)
		{
			return $setting;
		}

		$updates = array();

		if (empty($setting->agent_api_key))
		{
			$updates['agent_api_key'] = $this->generate_api_key();
		}

		if (empty($setting->monitoring_interval) || (int) $setting->monitoring_interval < 1)
		{
			$updates['monitoring_interval'] = 10;
		}

		if (empty($setting->process_retention_days) || (int) $setting->process_retention_days < 1)
		{
			$updates['process_retention_days'] = 7;
		}

		if (empty($setting->log_retention_days) || (int) $setting->log_retention_days < 1)
		{
			$updates['log_retention_days'] = 14;
		}

		if ( ! empty($updates))
		{
			$this->update_settings($setting->id, $updates);
			foreach ($updates as $key => $value)
			{
				$setting->{$key} = $value;
			}
		}

		return $setting;
	}
}
