<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Database_backup_model extends CI_Model
{
	protected $history_table = 'backup_history';
	protected $settings_table = 'database_backup_settings';

	public function __construct()
	{
		parent::__construct();
		$this->ensure_schema();
	}

	public function ensure_schema()
	{
		if ($this->db->table_exists($this->history_table))
		{
			$columns = array(
				'database_name' => "ALTER TABLE `backup_history` ADD COLUMN `database_name` VARCHAR(128) DEFAULT NULL AFTER `backup_type`",
				'file_name' => "ALTER TABLE `backup_history` ADD COLUMN `file_name` VARCHAR(255) DEFAULT NULL AFTER `remote_path`",
				'local_path' => "ALTER TABLE `backup_history` ADD COLUMN `local_path` VARCHAR(500) DEFAULT NULL AFTER `file_name`",
				'file_size_bytes' => "ALTER TABLE `backup_history` ADD COLUMN `file_size_bytes` BIGINT UNSIGNED DEFAULT NULL AFTER `local_path`",
				'completed_at' => "ALTER TABLE `backup_history` ADD COLUMN `completed_at` DATETIME DEFAULT NULL AFTER `created_at`",
			);

			foreach ($columns as $column => $sql)
			{
				if ( ! $this->db->field_exists($column, $this->history_table))
				{
					$this->db->query($sql);
				}
			}
		}

		if ( ! $this->db->table_exists($this->settings_table))
		{
			$this->db->query("
				CREATE TABLE `database_backup_settings` (
					`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					`storage_path` VARCHAR(255) NOT NULL DEFAULT 'uploads/backup/database',
					`backup_format` ENUM('sql') NOT NULL DEFAULT 'sql',
					`compression_zip` TINYINT(1) NOT NULL DEFAULT 0,
					`max_backups` INT UNSIGNED NOT NULL DEFAULT 20,
					`auto_delete_old` TINYINT(1) NOT NULL DEFAULT 1,
					`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`updated_at` DATETIME DEFAULT NULL,
					PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}
	}

	public function settings()
	{
		$row = $this->db
			->order_by('id', 'ASC')
			->limit(1)
			->get($this->settings_table)
			->row();

		if ($row)
		{
			return $row;
		}

		$this->db->insert($this->settings_table, array(
			'storage_path' => 'uploads/backup/database',
			'backup_format' => 'sql',
			'compression_zip' => 0,
			'max_backups' => 20,
			'auto_delete_old' => 1,
			'created_at' => date('Y-m-d H:i:s'),
		));

		return $this->settings();
	}

	public function update_settings($data)
	{
		$settings = $this->settings();

		return $this->db
			->where('id', $settings->id)
			->update($this->settings_table, array(
				'storage_path' => $data['storage_path'],
				'backup_format' => 'sql',
				'compression_zip' => ! empty($data['compression_zip']) ? 1 : 0,
				'max_backups' => (int) $data['max_backups'],
				'auto_delete_old' => ! empty($data['auto_delete_old']) ? 1 : 0,
				'updated_at' => date('Y-m-d H:i:s'),
			));
	}

	public function create($data)
	{
		$record = array(
			'server_id' => empty($data['server_id']) ? NULL : (int) $data['server_id'],
			'ssh_config_id' => (int) $data['ssh_config_id'],
			'user_id' => empty($data['user_id']) ? NULL : (int) $data['user_id'],
			'backup_type' => isset($data['backup_type']) ? $data['backup_type'] : 'database',
			'database_name' => isset($data['database_name']) ? $data['database_name'] : NULL,
			'action' => isset($data['action']) ? $data['action'] : 'backup',
			'remote_path' => isset($data['remote_path']) ? $data['remote_path'] : NULL,
			'file_name' => isset($data['file_name']) ? $data['file_name'] : NULL,
			'local_path' => isset($data['local_path']) ? $data['local_path'] : NULL,
			'file_size_bytes' => isset($data['file_size_bytes']) ? (int) $data['file_size_bytes'] : NULL,
			'status' => isset($data['status']) ? $data['status'] : 'pending',
			'output' => isset($data['output']) ? $data['output'] : NULL,
			'created_at' => isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s'),
			'completed_at' => isset($data['completed_at']) ? $data['completed_at'] : NULL,
		);

		$this->db->insert($this->history_table, $record);

		return (int) $this->db->insert_id();
	}

	public function update($id, $data)
	{
		return $this->db
			->where('id', (int) $id)
			->update($this->history_table, $data);
	}

	public function find($id)
	{
		return $this->db
			->select('backup_history.*, users.fullname, users.username, ssh_config.name AS ssh_name, servers.server_name')
			->from($this->history_table)
			->join('users', 'users.id = backup_history.user_id', 'left')
			->join('ssh_config', 'ssh_config.id = backup_history.ssh_config_id', 'left')
			->join('servers', 'servers.id = backup_history.server_id', 'left')
			->where('backup_history.id', (int) $id)
			->get()
			->row();
	}

	public function latest_database_backup($server_id = NULL)
	{
		$this->db
			->select('backup_history.*, users.fullname, users.username')
			->from($this->history_table)
			->join('users', 'users.id = backup_history.user_id', 'left')
			->where('backup_history.backup_type', 'database')
			->where('backup_history.action', 'backup')
			->where('backup_history.status', 'success');

		if ($server_id)
		{
			$this->db->where('backup_history.server_id', (int) $server_id);
		}

		return $this->db
			->order_by('backup_history.completed_at IS NULL', 'ASC', FALSE)
			->order_by('COALESCE(backup_history.completed_at, backup_history.created_at)', 'DESC', FALSE)
			->limit(1)
			->get()
			->row();
	}

	public function latest_restore($server_id = NULL)
	{
		$this->db
			->select('backup_history.*, users.fullname, users.username')
			->from($this->history_table)
			->join('users', 'users.id = backup_history.user_id', 'left')
			->where('backup_history.backup_type', 'database')
			->where('backup_history.action', 'restore')
			->where('backup_history.status', 'success');

		if ($server_id)
		{
			$this->db->where('backup_history.server_id', (int) $server_id);
		}

		return $this->db
			->order_by('COALESCE(backup_history.completed_at, backup_history.created_at)', 'DESC', FALSE)
			->limit(1)
			->get()
			->row();
	}

	public function all_database_backups($limit = 500)
	{
		return $this->db
			->select('backup_history.*, users.fullname, users.username, ssh_config.name AS ssh_name, servers.server_name')
			->from($this->history_table)
			->join('users', 'users.id = backup_history.user_id', 'left')
			->join('ssh_config', 'ssh_config.id = backup_history.ssh_config_id', 'left')
			->join('servers', 'servers.id = backup_history.server_id', 'left')
			->where('backup_history.backup_type', 'database')
			->order_by('backup_history.created_at', 'DESC')
			->limit($limit)
			->get()
			->result();
	}

	public function delete($id)
	{
		return $this->db
			->where('id', (int) $id)
			->delete($this->history_table);
	}

	public function prune_old_database_backups($keep, $storage_path)
	{
		$keep = max((int) $keep, 1);
		$rows = $this->db
			->where('backup_type', 'database')
			->where('action', 'backup')
			->where('status', 'success')
			->order_by('created_at', 'DESC')
			->get($this->history_table)
			->result();

		if (count($rows) <= $keep)
		{
			return 0;
		}

		$deleted = 0;
		foreach (array_slice($rows, $keep) as $row)
		{
			if ($row->local_path && strpos(str_replace('\\', '/', $row->local_path), trim($storage_path, '/').'/') === 0)
			{
				$absolute = FCPATH.$row->local_path;
				if (is_file($absolute))
				{
					@unlink($absolute);
				}
			}

			$this->delete($row->id);
			$deleted++;
		}

		return $deleted;
	}
}
