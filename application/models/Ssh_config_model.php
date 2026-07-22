<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ssh_config_model extends CI_Model
{
	protected $table = 'ssh_config';

	public function __construct()
	{
		parent::__construct();
		$this->load->library('encryption');
	}

	public function get_all($active_only = FALSE)
	{
		$this->db
			->select('ssh_config.*, servers.server_name, servers.hostname AS monitored_hostname')
			->from($this->table)
			->join('servers', 'servers.id = ssh_config.server_id', 'left')
			->order_by('ssh_config.name', 'ASC');

		if ($active_only)
		{
			$this->db->where('ssh_config.status', 'active');
		}

		return $this->db->get()->result();
	}

	public function find($id, $decrypt = FALSE)
	{
		$config = $this->db
			->select('ssh_config.*, servers.server_name, servers.hostname AS monitored_hostname')
			->from($this->table)
			->join('servers', 'servers.id = ssh_config.server_id', 'left')
			->where('ssh_config.id', (int) $id)
			->get()
			->row();

		$this->decrypt_config($config, $decrypt);

		return $config;
	}

	public function find_active_by_server($server_id, $decrypt = FALSE)
	{
		$config = $this->db
			->select('ssh_config.*, servers.server_name, servers.hostname AS monitored_hostname')
			->from($this->table)
			->join('servers', 'servers.id = ssh_config.server_id', 'left')
			->where('ssh_config.server_id', (int) $server_id)
			->where('ssh_config.status', 'active')
			->order_by('ssh_config.last_connected_at', 'DESC')
			->order_by('ssh_config.id', 'DESC')
			->limit(1)
			->get()
			->row();

		$this->decrypt_config($config, $decrypt);

		return $config;
	}

	public function first_active($decrypt = FALSE)
	{
		$config = $this->db
			->select('ssh_config.*, servers.server_name, servers.hostname AS monitored_hostname')
			->from($this->table)
			->join('servers', 'servers.id = ssh_config.server_id', 'left')
			->where('ssh_config.status', 'active')
			->order_by('ssh_config.last_connected_at', 'DESC')
			->order_by('ssh_config.id', 'DESC')
			->limit(1)
			->get()
			->row();

		$this->decrypt_config($config, $decrypt);

		return $config;
	}

	public function link_server($id, $server_id)
	{
		return $this->db
			->where('id', (int) $id)
			->update($this->table, array(
				'server_id' => (int) $server_id,
				'updated_at' => date('Y-m-d H:i:s'),
			));
	}

	public function create($data)
	{
		$record = $this->prepare_record($data, TRUE);
		$record['created_at'] = date('Y-m-d H:i:s');
		$record['updated_at'] = date('Y-m-d H:i:s');

		$this->db->insert($this->table, $record);

		return $this->db->insert_id();
	}

	public function update($id, $data)
	{
		$record = $this->prepare_record($data, FALSE);
		$record['updated_at'] = date('Y-m-d H:i:s');

		return $this->db
			->where('id', (int) $id)
			->update($this->table, $record);
	}

	public function delete($id)
	{
		return $this->db
			->where('id', (int) $id)
			->delete($this->table);
	}

	public function delete_with_server($id)
	{
		$config = $this->db
			->where('id', (int) $id)
			->get($this->table)
			->row();

		if ( ! $config)
		{
			return array('ok' => FALSE, 'server_deleted' => FALSE, 'message' => 'SSH config not found.');
		}

		$server_id = ! empty($config->server_id) ? (int) $config->server_id : 0;
		$agent_id = 'ssh-config-'.(int) $config->id;
		$server_deleted = FALSE;

		$this->load->model('Monitoring_model');
		$this->Monitoring_model->revoke_agent($agent_id, $server_id, 'SSH config deleted.');
		$this->db->trans_start();
		$this->db
			->where('id', (int) $config->id)
			->delete($this->table);

		if ($server_id && ! $this->server_has_configs($server_id))
		{
			$server_deleted = $this->Monitoring_model->delete_server((int) $server_id);
		}

		$this->db->trans_complete();

		return array(
			'ok' => (bool) $this->db->trans_status(),
			'server_deleted' => (bool) $server_deleted,
			'message' => $this->db->trans_status() ? 'Deleted.' : 'Delete failed.',
		);
	}

	public function touch_connected($id)
	{
		return $this->db
			->where('id', (int) $id)
			->update($this->table, array('last_connected_at' => date('Y-m-d H:i:s')));
	}

	protected function server_has_configs($server_id)
	{
		return $this->db
			->where('server_id', (int) $server_id)
			->count_all_results($this->table) > 0;
	}

	protected function prepare_record($data, $is_create)
	{
		$record = array(
			'server_id' => empty($data['server_id']) ? NULL : (int) $data['server_id'],
			'name' => $data['name'],
			'host' => $data['host'],
			'port' => (int) $data['port'],
			'username' => $data['username'],
			'auth_type' => $data['auth_type'],
			'status' => isset($data['status']) ? $data['status'] : 'active',
		);

		foreach (array('password', 'private_key', 'passphrase') as $field)
		{
			if ($is_create || (isset($data[$field]) && $data[$field] !== ''))
			{
				$record[$field.'_encrypted'] = $this->encrypt_value(isset($data[$field]) ? $data[$field] : NULL);
			}
		}

		if (isset($data['created_by']))
		{
			$record['created_by'] = $data['created_by'];
		}

		return $record;
	}

	protected function encrypt_value($value)
	{
		if ($value === NULL || $value === '')
		{
			return NULL;
		}

		return $this->encryption->encrypt($value);
	}

	protected function decrypt_value($value)
	{
		if ( ! $value)
		{
			return NULL;
		}

		return $this->encryption->decrypt($value);
	}

	protected function decrypt_config($config, $decrypt)
	{
		if ($config && $decrypt)
		{
			$config->password = $this->decrypt_value($config->password_encrypted);
			$config->private_key = $this->decrypt_value($config->private_key_encrypted);
			$config->passphrase = $this->decrypt_value($config->passphrase_encrypted);
			$config->password_decrypt_failed = ! empty($config->password_encrypted) && $config->password === FALSE;
			$config->private_key_decrypt_failed = ! empty($config->private_key_encrypted) && $config->private_key === FALSE;
			$config->passphrase_decrypt_failed = ! empty($config->passphrase_encrypted) && $config->passphrase === FALSE;
			$config->password = $config->password === FALSE ? NULL : $config->password;
			$config->private_key = $config->private_key === FALSE ? NULL : $config->private_key;
			$config->passphrase = $config->passphrase === FALSE ? NULL : $config->passphrase;
		}

		return $config;
	}
}
