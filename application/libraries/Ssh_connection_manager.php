<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

class Ssh_connection_manager
{
	protected $ssh_pool = array();
	protected $sftp_pool = array();
	protected $key_pool = array();
	protected $last_error = '';

	public function __construct()
	{
		register_shutdown_function(array($this, 'disconnect'));
	}

	public function available()
	{
		return class_exists('phpseclib3\\Net\\SSH2') && class_exists('phpseclib3\\Net\\SFTP');
	}

	public function last_error()
	{
		return $this->last_error;
	}

	public function ssh($config, $timeout = 10, $force_reconnect = FALSE)
	{
		return $this->connection('ssh', $config, $timeout, $force_reconnect);
	}

	public function sftp($config, $timeout = 10, $force_reconnect = FALSE)
	{
		return $this->connection('sftp', $config, $timeout, $force_reconnect);
	}

	public function reconnect_ssh($config, $timeout = 10)
	{
		return $this->ssh($config, $timeout, TRUE);
	}

	public function reconnect_sftp($config, $timeout = 10)
	{
		return $this->sftp($config, $timeout, TRUE);
	}

	public function disconnect($config = NULL)
	{
		if ($config === NULL)
		{
			$this->disconnect_pool($this->ssh_pool);
			$this->disconnect_pool($this->sftp_pool);
			$this->ssh_pool = array();
			$this->sftp_pool = array();

			return TRUE;
		}

		$this->disconnect_key($this->ssh_pool, $this->pool_key($config, 'ssh'));
		$this->disconnect_key($this->sftp_pool, $this->pool_key($config, 'sftp'));

		return TRUE;
	}

	protected function connection($type, $config, $timeout, $force_reconnect)
	{
		if ( ! $this->available())
		{
			$this->last_error = 'phpseclib is not installed.';
			return FALSE;
		}

		$pool_key = $this->pool_key($config, $type);
		$pool =& $this->pool($type);

		if ($force_reconnect)
		{
			$this->disconnect_key($pool, $pool_key);
		}

		if (isset($pool[$pool_key]) && $this->is_ready($pool[$pool_key]))
		{
			$pool[$pool_key]->setTimeout($timeout);
			return $pool[$pool_key];
		}

		$this->disconnect_key($pool, $pool_key);

		try
		{
			$client = $type === 'sftp'
				? new SFTP($config->host, (int) $config->port, $timeout)
				: new SSH2($config->host, (int) $config->port, $timeout);
			$client->setTimeout($timeout);

			if ( ! $this->login($client, $config))
			{
				return FALSE;
			}
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return FALSE;
		}

		$pool[$pool_key] = $client;

		return $pool[$pool_key];
	}

	protected function &pool($type)
	{
		if ($type === 'sftp')
		{
			return $this->sftp_pool;
		}

		return $this->ssh_pool;
	}

	protected function is_ready($client)
	{
		try
		{
			return $client && $client->isConnected() && $client->isAuthenticated();
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
			return FALSE;
		}
	}

	protected function login($client, $config)
	{
		if ($config->auth_type === 'private_key')
		{
			if (empty($config->private_key))
			{
				if ( ! empty($config->private_key_decrypt_failed))
				{
					$this->last_error = 'Private key SSH tidak bisa didecrypt. Kemungkinan APP_ENCRYPTION_KEY berubah. Edit SSH Config lalu paste dan simpan ulang private key.';
					return FALSE;
				}

				if ( ! empty($config->private_key_encrypted))
				{
					$this->last_error = 'Private key SSH tersimpan, tetapi tidak bisa dibaca. Edit SSH Config lalu simpan ulang private key.';
					return FALSE;
				}

				$this->last_error = 'Private key SSH kosong. Edit SSH Config lalu isi private key.';
				return FALSE;
			}

			try
			{
				$key = $this->private_key($config);
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

	protected function private_key($config)
	{
		$key_id = sha1((string) $config->private_key.'|'.(string) $config->passphrase);

		if ( ! isset($this->key_pool[$key_id]))
		{
			$this->key_pool[$key_id] = PublicKeyLoader::load($config->private_key, $config->passphrase ?: FALSE);
		}

		return $this->key_pool[$key_id];
	}

	protected function pool_key($config, $type)
	{
		$id = isset($config->id) ? (int) $config->id : 0;
		$host = isset($config->host) ? $config->host : '';
		$port = isset($config->port) ? (int) $config->port : 22;
		$username = isset($config->username) ? $config->username : '';
		$auth_type = isset($config->auth_type) ? $config->auth_type : 'password';
		$secret = $auth_type === 'private_key'
			? sha1((string) (isset($config->private_key) ? $config->private_key : '').'|'.(string) (isset($config->passphrase) ? $config->passphrase : ''))
			: sha1((string) (isset($config->password) ? $config->password : ''));

		return $type.'|'.$id.'|'.$host.'|'.$port.'|'.$username.'|'.$auth_type.'|'.$secret;
	}

	protected function disconnect_pool(&$pool)
	{
		foreach (array_keys($pool) as $key)
		{
			$this->disconnect_key($pool, $key);
		}
	}

	protected function disconnect_key(&$pool, $key)
	{
		if (empty($pool[$key]))
		{
			unset($pool[$key]);
			return TRUE;
		}

		try
		{
			$pool[$key]->disconnect();
		}
		catch (Exception $e)
		{
			$this->last_error = $this->friendly_error($e->getMessage());
		}

		unset($pool[$key]);

		return TRUE;
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
}
