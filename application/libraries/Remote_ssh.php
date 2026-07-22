<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use phpseclib3\Net\SFTP;

class Remote_ssh
{
	protected $CI;
	protected $last_error = '';

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('Ssh_connection_manager');
	}

	public function available()
	{
		return $this->CI->ssh_connection_manager->available();
	}

	public function last_error()
	{
		return $this->last_error;
	}

	public function test($config)
	{
		$result = $this->execute($config, 'printf "connected:%s" "$(hostname)"', 10);

		if ( ! $result['ok'])
		{
			return $result;
		}

		$result['output'] = trim($result['output']);

		return $result;
	}

	public function execute($config, $command, $timeout = 30)
	{
		$ssh = $this->connect_ssh($config, $timeout);

		if ( ! $ssh)
		{
			return array('ok' => FALSE, 'output' => $this->last_error, 'exit_status' => 1);
		}

		$started = microtime(TRUE);
		$result = $this->exec_on_connection($ssh, $command, $started);

		if ( ! $result['ok'] && ! empty($result['connection_error']))
		{
			$ssh = $this->connect_ssh($config, $timeout, TRUE);

			if ( ! $ssh)
			{
				return array(
					'ok' => FALSE,
					'output' => $this->last_error,
					'exit_status' => 1,
					'duration_ms' => (int) ((microtime(TRUE) - $started) * 1000),
				);
			}

			$result = $this->exec_on_connection($ssh, $command, $started);
			$result['reconnected'] = TRUE;
		}

		unset($result['connection_error']);

		return $result;
	}

	protected function exec_on_connection($ssh, $command, $started)
	{
		try
		{
			$output = $ssh->exec($command);
			$exit_status = $ssh->getExitStatus();

			if ($output === FALSE)
			{
				$this->last_error = 'SSH command failed because the connection was closed.';

				return array(
					'ok' => FALSE,
					'output' => $this->last_error,
					'exit_status' => 1,
					'duration_ms' => (int) ((microtime(TRUE) - $started) * 1000),
					'connection_error' => TRUE,
				);
			}
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());

			return array(
				'ok' => FALSE,
				'output' => $this->last_error,
				'exit_status' => 1,
				'duration_ms' => (int) ((microtime(TRUE) - $started) * 1000),
				'connection_error' => TRUE,
			);
		}

		return array(
			'ok' => $exit_status === 0 || $exit_status === NULL,
			'output' => $output,
			'exit_status' => $exit_status,
			'duration_ms' => (int) ((microtime(TRUE) - $started) * 1000),
		);
	}

	public function sftp($config)
	{
		$sftp = $this->CI->ssh_connection_manager->sftp($config, 10);

		if ( ! $sftp)
		{
			$this->last_error = $this->CI->ssh_connection_manager->last_error();
			return FALSE;
		}

		return $sftp;
	}

	public function list_dir($config, $path)
	{
		$sftp = $this->sftp($config);

		if ( ! $sftp)
		{
			return array('ok' => FALSE, 'message' => $this->last_error, 'items' => array());
		}

		$path = $this->normalize_path($path);

		try
		{
			$items = $sftp->rawlist($path);
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'message' => $this->last_error, 'items' => array());
		}

		if ($items === FALSE)
		{
			return array('ok' => FALSE, 'message' => 'Unable to read remote directory.', 'items' => array());
		}

		$result = array();
		foreach ($items as $name => $meta)
		{
			if ($name === '.' || $name === '..')
			{
				continue;
			}

			$result[] = array(
				'name' => $name,
				'path' => rtrim($path, '/').'/'.$name,
				'type' => isset($meta['type']) && $meta['type'] === NET_SFTP_TYPE_DIRECTORY ? 'folder' : 'file',
				'permission' => substr(sprintf('%o', isset($meta['mode']) ? $meta['mode'] : 0), -4),
				'owner' => isset($meta['uid']) ? $meta['uid'] : '',
				'size' => isset($meta['size']) ? $meta['size'] : 0,
				'last_modified' => isset($meta['mtime']) ? date('Y-m-d H:i:s', $meta['mtime']) : '',
			);
		}

		return array('ok' => TRUE, 'message' => 'OK', 'items' => $result);
	}

	public function read_file($config, $path)
	{
		$sftp = $this->sftp($config);

		if ( ! $sftp)
		{
			return array('ok' => FALSE, 'message' => $this->last_error, 'content' => '');
		}

		try
		{
			$content = $sftp->get($this->normalize_path($path));
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'message' => $this->last_error, 'content' => '');
		}

		if ($content === FALSE)
		{
			return array('ok' => FALSE, 'message' => 'Unable to read remote file.', 'content' => '');
		}

		return array('ok' => TRUE, 'message' => 'OK', 'content' => $content);
	}

	public function write_file($config, $path, $content)
	{
		$sftp = $this->sftp($config);

		if ( ! $sftp)
		{
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		try
		{
			$ok = $sftp->put($this->normalize_path($path), $content);
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		return array('ok' => (bool) $ok, 'message' => $ok ? 'Saved.' : 'Unable to save remote file.');
	}

	public function delete_path($config, $path)
	{
		$sftp = $this->sftp($config);

		if ( ! $sftp)
		{
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		$path = $this->normalize_path($path);

		try
		{
			$ok = $sftp->delete($path, TRUE);
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		return array('ok' => (bool) $ok, 'message' => $ok ? 'Deleted.' : 'Unable to delete remote path.');
	}

	public function mkdir($config, $path)
	{
		$sftp = $this->sftp($config);

		if ( ! $sftp)
		{
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		try
		{
			$ok = $sftp->mkdir($this->normalize_path($path), -1, TRUE);
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		return array('ok' => (bool) $ok, 'message' => $ok ? 'Folder created.' : 'Unable to create folder.');
	}

	public function rename($config, $source, $target)
	{
		$sftp = $this->sftp($config);

		if ( ! $sftp)
		{
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		try
		{
			$ok = $sftp->rename($this->normalize_path($source), $this->normalize_path($target));
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		return array('ok' => (bool) $ok, 'message' => $ok ? 'Renamed.' : 'Unable to rename path.');
	}

	public function upload($config, $remote_path, $local_path)
	{
		$sftp = $this->sftp($config);

		if ( ! $sftp)
		{
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		try
		{
			$ok = $sftp->put($this->normalize_path($remote_path), $local_path, SFTP::SOURCE_LOCAL_FILE);
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		return array('ok' => (bool) $ok, 'message' => $ok ? 'Uploaded.' : 'Unable to upload file.');
	}

	protected function connect_ssh($config, $timeout = 10, $force_reconnect = FALSE)
	{
		$ssh = $this->CI->ssh_connection_manager->ssh($config, $timeout, $force_reconnect);

		if ( ! $ssh)
		{
			$this->last_error = $this->CI->ssh_connection_manager->last_error();
			return FALSE;
		}

		return $ssh;
	}

	protected function friendly_error($message)
	{
		$message = trim((string) $message);
		$lower = strtolower($message);

		if (strpos($lower, 'cannot connect') !== FALSE || strpos($lower, 'connection attempt failed') !== FALSE || strpos($lower, 'timed out') !== FALSE || strpos($lower, 'error 10060') !== FALSE)
		{
			return 'Tidak bisa konek ke SSH host/port. Cek public IP, port SSH, Azure NSG inbound TCP 22, firewall server, dan pastikan service SSH aktif. Detail: '.$message;
		}

		if (strpos($lower, 'connection refused') !== FALSE)
		{
			return 'Koneksi SSH ditolak oleh server. Biasanya port benar tetapi service SSH tidak aktif atau firewall menolak koneksi. Detail: '.$message;
		}

		return $message === '' ? 'SSH connection failed.' : $message;
	}

	protected function normalize_path($path)
	{
		$path = trim((string) $path);

		return $path === '' ? '/' : $path;
	}
}
