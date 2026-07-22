<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Remote_history_model extends CI_Model
{
	public function terminal($ssh_config_id, $user_id, $command, $output, $exit_status, $status, $started_at)
	{
		$this->db->insert('terminal_history', array(
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'command' => $command,
			'status' => $status,
			'executed_at' => date('Y-m-d H:i:s'),
		));

		return $this->db->insert('terminal_logs', array(
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'command' => $command,
			'output' => $output,
			'exit_status' => $exit_status,
			'status' => $status,
			'started_at' => $started_at,
			'finished_at' => date('Y-m-d H:i:s'),
		));
	}

	public function service($server_id, $ssh_config_id, $user_id, $service, $action, $status, $output)
	{
		return $this->db->insert('service_history', array(
			'server_id' => $server_id,
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'service_name' => $service,
			'action' => $action,
			'status' => $status,
			'output' => $output,
			'created_at' => date('Y-m-d H:i:s'),
		));
	}

	public function backup($server_id, $ssh_config_id, $user_id, $type, $action, $path, $status, $output)
	{
		return $this->db->insert('backup_history', array(
			'server_id' => $server_id,
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'backup_type' => $type,
			'action' => $action,
			'remote_path' => $path,
			'status' => $status,
			'output' => $output,
			'created_at' => date('Y-m-d H:i:s'),
		));
	}

	public function file($server_id, $ssh_config_id, $user_id, $action, $source, $target, $status, $message)
	{
		return $this->db->insert('file_history', array(
			'server_id' => $server_id,
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'action' => $action,
			'source_path' => $source,
			'target_path' => $target,
			'status' => $status,
			'message' => $message,
			'created_at' => date('Y-m-d H:i:s'),
		));
	}

	public function firewall($server_id, $ssh_config_id, $user_id, $type, $action, $rule, $status, $output)
	{
		return $this->db->insert('firewall_history', array(
			'server_id' => $server_id,
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'firewall_type' => $type,
			'action' => $action,
			'rule' => $rule,
			'status' => $status,
			'output' => $output,
			'created_at' => date('Y-m-d H:i:s'),
		));
	}

	public function ssl($server_id, $ssh_config_id, $user_id, $domain, $action, $status, $output)
	{
		return $this->db->insert('ssl_history', array(
			'server_id' => $server_id,
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'domain' => $domain,
			'action' => $action,
			'status' => $status,
			'output' => $output,
			'created_at' => date('Y-m-d H:i:s'),
		));
	}

	public function cron($server_id, $ssh_config_id, $user_id, $action, $expression, $command, $enabled, $status, $output)
	{
		return $this->db->insert('cron_history', array(
			'server_id' => $server_id,
			'ssh_config_id' => $ssh_config_id,
			'user_id' => $user_id,
			'action' => $action,
			'cron_expression' => $expression,
			'command' => $command,
			'enabled' => $enabled ? 1 : 0,
			'status' => $status,
			'output' => $output,
			'created_at' => date('Y-m-d H:i:s'),
		));
	}

	public function recent($table, $limit = 50)
	{
		return $this->db
			->order_by('created_at', 'DESC')
			->limit($limit)
			->get($table)
			->result();
	}
}
