<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

class Remote_ssh
{
	protected $CI;
	protected $last_error = '';

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function available()
	{
		return class_exists('phpseclib3\\Net\\SSH2') && class_exists('phpseclib3\\Net\\SFTP');
	}

	public function last_error()
	{
		return $this->last_error;
	}

	public function test($config)
	{
		$ssh = $this->connect_ssh($config);

		if ( ! $ssh)
		{
			return array('ok' => FALSE, 'output' => $this->last_error, 'exit_status' => 1);
		}

		try
		{
			$output = trim($ssh->exec('printf "connected:%s" "$(hostname)"'));
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return array('ok' => FALSE, 'output' => $this->last_error, 'exit_status' => 1);
		}

		return array('ok' => TRUE, 'output' => $output, 'exit_status' => 0);
	}

	public function execute($config, $command, $timeout = 30)
	{
		$ssh = $this->connect_ssh($config, $timeout);

		if ( ! $ssh)
		{
			return array('ok' => FALSE, 'output' => $this->last_error, 'exit_status' => 1);
		}

		$started = microtime(TRUE);

		try
		{
			$output = $ssh->exec($command);
			$exit_status = $ssh->getExitStatus();
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());

			return array(
				'ok' => FALSE,
				'output' => $this->last_error,
				'exit_status' => 1,
				'duration_ms' => (int) ((microtime(TRUE) - $started) * 1000),
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
		if ( ! $this->available())
		{
			$this->last_error = 'phpseclib is not installed.';
			return FALSE;
		}

		try
		{
			$sftp = new SFTP($config->host, (int) $config->port, 10);

			if ( ! $this->login($sftp, $config))
			{
				return FALSE;
			}
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
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

	protected function connect_ssh($config, $timeout = 10)
	{
		if ( ! $this->available())
		{
			$this->last_error = 'phpseclib is not installed.';
			return FALSE;
		}

		try
		{
			$ssh = new SSH2($config->host, (int) $config->port, $timeout);
			$ssh->setTimeout($timeout);

			if ( ! $this->login($ssh, $config))
			{
				return FALSE;
			}
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return FALSE;
		}

		return $ssh;
	}

	protected function login($client, $config)
	{
		if ($config->auth_type === 'private_key')
		{
			if (empty($config->private_key))
			{
				$this->last_error = 'Private key is empty.';
				return FALSE;
			}

			try
			{
				$key = PublicKeyLoader::load($config->private_key, $config->passphrase ?: FALSE);
			}
			catch (Exception $e)
			{
				$this->last_error = 'Private key could not be loaded.';
				return FALSE;
			}

			try
			{
				$logged_in = $client->login($config->username, $key);
			}
			catch (Exception $e)
			{
				$this->last_error = $this->friendly_error($e->getMessage());
				return FALSE;
			}

			if ($logged_in)
			{
				return TRUE;
			}
		}
		else
		{
			try
			{
				$logged_in = $client->login($config->username, (string) $config->password);
			}
			catch (Exception $e)
			{
				$this->last_error = $this->friendly_error($e->getMessage());
				return FALSE;
			}

			if ($logged_in)
			{
				return TRUE;
			}
		}

		$this->last_error = 'SSH authentication failed.';

		return FALSE;
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
