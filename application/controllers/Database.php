<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Database extends MY_Controller
{
	public function __construct()
	{
		parent::__construct(array('Super Admin', 'Admin', 'Operator'));
		$this->load->model('Monitoring_model');
		$this->load->model('Ssh_config_model');
		$this->load->model('Remote_history_model');
		$this->load->model('Database_backup_model');
		$this->load->library('Remote_ssh');
		$this->load->library('Remote_metric_collector');
		$this->load->library('Remote_database');
	}

	public function index()
	{
		$server_id = (int) $this->input->get('server_id', TRUE);
		$this->refresh_database_metrics($server_id);
		$monitoring = $this->Monitoring_model->dashboard_payload($server_id);
		$config = $this->selected_config($this->input->get('ssh_config_id', TRUE), isset($monitoring['selected_server_id']) ? $monitoring['selected_server_id'] : 0);
		$statistics = $config ? $this->remote_database->statistics($config) : array('ok' => FALSE, 'message' => 'SSH Config belum tersedia.');
		$server_id = isset($monitoring['selected_server_id']) ? (int) $monitoring['selected_server_id'] : 0;

		$this->render('database/index', array(
			'page_title' => 'Database',
			'monitoring' => $monitoring,
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'selected_config' => $config,
			'statistics' => $statistics,
			'backup_settings' => $this->Database_backup_model->settings(),
			'last_backup' => $this->Database_backup_model->latest_database_backup($server_id),
			'last_restore' => $this->Database_backup_model->latest_restore($server_id),
			'recent_backups' => $this->Database_backup_model->all_database_backups(10),
		));
	}

	public function action()
	{
		$this->require_post();
		$config = $this->Ssh_config_model->find((int) $this->input->post('ssh_config_id', TRUE), TRUE);
		$action = $this->input->post('database_action', TRUE);
		$target = trim((string) $this->input->post('database_name', TRUE));
		$allowed = array('status', 'restart', 'backup', 'optimize', 'repair', 'running_query', 'export_sql');

		if ( ! $config || ! in_array($action, $allowed, TRUE) || ! $this->valid_optional_name($target))
		{
			$this->session->set_flashdata('error', 'Database action tidak valid.');
			redirect('database');
		}

		if ($action === 'backup' || $action === 'export_sql')
		{
			$this->run_backup_action($config, $target, $action === 'export_sql');
			return;
		}

		if ($action === 'status')
		{
			$result = $this->remote_database->statistics($config, $target);
			$output = $result['ok'] ? json_encode($result, JSON_PRETTY_PRINT) : $result['message'];
		}
		elseif ($action === 'running_query')
		{
			$result = $this->remote_database->query($config, 'SHOW FULL PROCESSLIST', '', $target);
			$output = $result['ok'] ? $this->query_output_text($result) : $result['message'];
		}
		else
		{
			$result = $this->remote_ssh->execute($config, $this->database_maintenance_command($action, $target), 180);
			$output = $result['output'];
		}

		$status = ! empty($result['ok']) ? 'success' : 'failed';
		$this->Activity_model->log($this->current_user['id'], 'Database '.$action, $this->input->ip_address(), $config->server_id, $status, $output);
		$this->session->set_flashdata($status === 'success' ? 'success' : 'error', '<pre class="text-left mb-0">'.html_escape($output).'</pre>');

		redirect('database');
	}

	public function history()
	{
		$this->render('database/history', array(
			'page_title' => 'Backup History',
			'history' => $this->Database_backup_model->all_database_backups(),
		));
	}

	public function download($id)
	{
		$backup = $this->Database_backup_model->find((int) $id);

		if ( ! $backup || $backup->backup_type !== 'database' || ! $backup->local_path)
		{
			show_404();
		}

		$absolute = $this->safe_local_backup_path($backup->local_path);
		if ( ! $absolute || ! is_file($absolute))
		{
			$this->session->set_flashdata('error', 'File backup tidak ditemukan.');
			redirect('database/history');
		}

		$this->Activity_model->log($this->current_user['id'], 'Download database backup: '.$backup->file_name, $this->input->ip_address(), $backup->server_id, 'success', $backup->local_path);
		$this->output_file_download($absolute, $backup->file_name ?: basename($absolute));
	}

	public function restore($id)
	{
		$this->require_post();
		$backup = $this->Database_backup_model->find((int) $id);

		if ( ! $backup || $backup->backup_type !== 'database')
		{
			show_404();
		}

		$config = $this->Ssh_config_model->find((int) $backup->ssh_config_id, TRUE);
		$absolute = $this->safe_local_backup_path($backup->local_path);

		if ( ! $config || ! $absolute || ! is_file($absolute))
		{
			$this->session->set_flashdata('error', 'File backup atau SSH Config tidak valid.');
			redirect('database/history');
		}

		$remote_path = '/tmp/restore-'.date('Ymd-His').'-'.basename($absolute);
		if ( ! $this->remote_database->upload_local_file($config, $absolute, $remote_path))
		{
			$this->session->set_flashdata('error', 'Upload file restore gagal: '.html_escape($this->remote_database->last_error()));
			redirect('database/history');
		}

		$result = $this->remote_database->restore($config, $remote_path, $backup->database_name);
		$status = ! empty($result['ok']) ? 'success' : 'failed';
		$output = $result['ok'] ? $result['message'] : $result['message'];
		$this->Database_backup_model->create(array(
			'server_id' => $backup->server_id,
			'ssh_config_id' => $backup->ssh_config_id,
			'user_id' => $this->current_user['id'],
			'backup_type' => 'database',
			'database_name' => $backup->database_name,
			'action' => 'restore',
			'remote_path' => $remote_path,
			'file_name' => $backup->file_name,
			'local_path' => $backup->local_path,
			'file_size_bytes' => $backup->file_size_bytes,
			'status' => $status,
			'output' => $output,
			'completed_at' => date('Y-m-d H:i:s'),
		));
		$this->Activity_model->log($this->current_user['id'], 'Restore database backup: '.$backup->file_name, $this->input->ip_address(), $backup->server_id, $status, $output);
		$this->session->set_flashdata($status === 'success' ? 'success' : 'error', $status === 'success' ? 'Restore database berhasil.' : 'Restore gagal: '.html_escape($output));

		redirect('database/history');
	}

	public function delete_backup($id)
	{
		$this->require_post();
		$backup = $this->Database_backup_model->find((int) $id);

		if ( ! $backup || $backup->backup_type !== 'database')
		{
			show_404();
		}

		$absolute = $this->safe_local_backup_path($backup->local_path);
		if ($absolute && is_file($absolute))
		{
			@unlink($absolute);
		}

		$this->Activity_model->log($this->current_user['id'], 'Delete database backup: '.$backup->file_name, $this->input->ip_address(), $backup->server_id, 'success', $backup->local_path);
		$this->Database_backup_model->delete($backup->id);
		$this->session->set_flashdata('success', 'Backup berhasil dihapus.');

		redirect('database/history');
	}

	public function explorer()
	{
		$config = $this->selected_config($this->input->get('ssh_config_id', TRUE));
		$result = $config ? $this->remote_database->list_databases($config) : array('ok' => FALSE, 'message' => 'SSH Config belum tersedia.', 'databases' => array());

		$this->render('database/explorer', array(
			'page_title' => 'Database Explorer',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'selected_config' => $config,
			'result' => $result,
		));
	}

	public function tables()
	{
		$config = $this->selected_config($this->input->get('ssh_config_id', TRUE));
		$database = trim((string) $this->input->get('database', TRUE));

		if ( ! $this->valid_required_name($database))
		{
			show_error('Database tidak valid.', 422);
		}

		$result = $config ? $this->remote_database->list_tables($config, $database) : array('ok' => FALSE, 'message' => 'SSH Config belum tersedia.', 'tables' => array());

		$this->render('database/tables', array(
			'page_title' => 'Table Explorer',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'selected_config' => $config,
			'database' => $database,
			'result' => $result,
		));
	}

	public function table_detail()
	{
		$config = $this->selected_config($this->input->get('ssh_config_id', TRUE));
		$database = trim((string) $this->input->get('database', TRUE));
		$table = trim((string) $this->input->get('table', TRUE));

		if ( ! $this->valid_required_name($database) || ! $this->valid_required_name($table))
		{
			show_error('Database atau tabel tidak valid.', 422);
		}

		$result = $config ? $this->remote_database->table_detail($config, $database, $table) : array('ok' => FALSE, 'message' => 'SSH Config belum tersedia.', 'columns' => array());

		$this->render('database/table_detail', array(
			'page_title' => 'Table Detail',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'selected_config' => $config,
			'database' => $database,
			'table' => $table,
			'result' => $result,
		));
	}

	public function data()
	{
		$config = $this->selected_config($this->input->get('ssh_config_id', TRUE));
		$database = trim((string) $this->input->get('database', TRUE));
		$table = trim((string) $this->input->get('table', TRUE));
		$page = max((int) $this->input->get('page', TRUE), 1);
		$search = trim((string) $this->input->get('search', TRUE));

		if ( ! $this->valid_required_name($database) || ! $this->valid_required_name($table))
		{
			show_error('Database atau tabel tidak valid.', 422);
		}

		$result = $config ? $this->remote_database->table_data($config, $database, $table, $page, 100, $search) : array('ok' => FALSE, 'message' => 'SSH Config belum tersedia.', 'columns' => array(), 'rows' => array(), 'total' => 0);

		$this->render('database/data', array(
			'page_title' => 'Data Viewer',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'selected_config' => $config,
			'database' => $database,
			'table' => $table,
			'page' => $page,
			'search' => $search,
			'result' => $result,
		));
	}

	public function query()
	{
		$config = $this->selected_config($this->input->get_post('ssh_config_id', TRUE));
		$query = trim((string) $this->input->post('sql_query', FALSE));
		$database = trim((string) $this->input->post('database', TRUE));
		$result = NULL;

		if ($this->input->method(TRUE) === 'POST')
		{
			if ( ! $config || ! $this->allowed_sql_query($query))
			{
				$result = array('ok' => FALSE, 'message' => 'Role Anda tidak diizinkan menjalankan query ini atau SSH Config tidak valid.');
			}
			else
			{
				$result = $this->remote_database->query($config, $query, $database);
			}

			$status = ! empty($result['ok']) ? 'success' : 'failed';
			$this->Activity_model->log($this->current_user['id'], 'SQL Query: '.$this->sql_action($query), $this->input->ip_address(), $config ? $config->server_id : NULL, $status, $query."\n\n".(isset($result['message']) ? $result['message'] : 'OK'));
		}

		$this->render('database/query', array(
			'page_title' => 'SQL Query',
			'configs' => $this->Ssh_config_model->get_all(TRUE),
			'selected_config' => $config,
			'database' => $database,
			'sql_query' => $query,
			'result' => $result,
		));
	}

	public function backup_config()
	{
		$this->render('database/backup_config', array(
			'page_title' => 'Backup Configuration',
			'settings' => $this->Database_backup_model->settings(),
			'configs' => $this->Ssh_config_model->get_all(TRUE),
		));
	}

	public function update_backup_config()
	{
		$this->require_post();
		$storage_path = trim((string) $this->input->post('storage_path', TRUE));
		$max_backups = (int) $this->input->post('max_backups', TRUE);

		if ( ! preg_match('/^[a-zA-Z0-9_\-\/]+$/', $storage_path) || strpos($storage_path, '..') !== FALSE || $max_backups < 1)
		{
			$this->session->set_flashdata('error', 'Konfigurasi backup tidak valid.');
			redirect('database/backup-config');
		}

		$this->Database_backup_model->update_settings(array(
			'storage_path' => trim($storage_path, '/'),
			'compression_zip' => $this->input->post('compression_zip') ? 1 : 0,
			'max_backups' => $max_backups,
			'auto_delete_old' => $this->input->post('auto_delete_old') ? 1 : 0,
		));
		$this->session->set_flashdata('success', 'Konfigurasi backup berhasil disimpan.');

		redirect('database/backup-config');
	}

	protected function run_backup_action($config, $target, $download)
	{
		$settings = $this->Database_backup_model->settings();
		$dir = $this->ensure_backup_dir($settings->storage_path);

		if ( ! $dir)
		{
			$this->session->set_flashdata('error', 'Folder backup lokal tidak bisa dibuat.');
			redirect('database');
		}

		$extension = ! $download && (int) $settings->compression_zip === 1 ? 'zip' : 'sql';
		$file_name = 'database_backup_'.date('Ymd_His').'.'.$extension;
		$relative = trim($settings->storage_path, '/').'/'.$file_name;
		$local_path = FCPATH.$relative;
		$remote_path = '/tmp/'.$file_name;
		$history_id = $this->Database_backup_model->create(array(
			'server_id' => $config->server_id,
			'ssh_config_id' => $config->id,
			'user_id' => $this->current_user['id'],
			'backup_type' => 'database',
			'database_name' => $target,
			'action' => 'backup',
			'remote_path' => $remote_path,
			'file_name' => $file_name,
			'local_path' => $relative,
			'status' => 'pending',
		));

		$result = $this->remote_database->backup($config, $target, $remote_path, '', $extension === 'zip');
		if (empty($result['ok']))
		{
			$this->finish_backup_history($history_id, 'failed', $result['message'], $relative, $remote_path, $file_name);
			$this->Activity_model->log($this->current_user['id'], 'Database backup failed', $this->input->ip_address(), $config->server_id, 'failed', $result['message']);
			$this->notify_backup_event($config->server_id, FALSE, $result['message'], $file_name, $history_id);
			$this->session->set_flashdata('error', 'Backup gagal: '.html_escape($result['message']));
			redirect('database');
		}

		if ( ! $this->remote_database->download_remote_file($config, $result['remote_path'], $local_path))
		{
			$message = 'Download file backup dari VPS gagal: '.$this->remote_database->last_error();
			$this->finish_backup_history($history_id, 'failed', $message, $relative, $result['remote_path'], $file_name);
			$this->Activity_model->log($this->current_user['id'], 'Database backup download failed', $this->input->ip_address(), $config->server_id, 'failed', $message);
			$this->notify_backup_event($config->server_id, FALSE, $message, $file_name, $history_id);
			$this->session->set_flashdata('error', html_escape($message));
			redirect('database');
		}

		$file_size = is_file($local_path) ? filesize($local_path) : (int) $result['file_size_bytes'];
		$message = 'Backup berhasil: '.$file_name.' ('.$this->format_bytes($file_size).')';
		$this->finish_backup_history($history_id, 'success', $message, $relative, $result['remote_path'], $file_name, $file_size);
		$this->Activity_model->log($this->current_user['id'], $download ? 'Export SQL database' : 'Backup database', $this->input->ip_address(), $config->server_id, 'success', $message);
		$this->notify_backup_event($config->server_id, TRUE, $message, $file_name, $history_id);

		if ((int) $settings->auto_delete_old === 1)
		{
			$this->Database_backup_model->prune_old_database_backups((int) $settings->max_backups, trim($settings->storage_path, '/'));
		}

		if ($download)
		{
			$this->output_file_download($local_path, $file_name);
			return;
		}

		$this->session->set_flashdata('success', $message);
		redirect('database');
	}

	protected function notify_backup_event($server_id, $success, $message, $file_name, $event_id)
	{
		try
		{
			$this->load->library('Telegram_notifier');
			$this->telegram_notifier->backup($server_id, $success, $message, $file_name, $event_id);
		}
		catch (Exception $e)
		{
			log_message('error', 'Telegram backup notification failed: '.$e->getMessage());
		}
	}

	protected function finish_backup_history($id, $status, $output, $local_path, $remote_path, $file_name, $file_size = NULL)
	{
		return $this->Database_backup_model->update($id, array(
			'status' => $status,
			'output' => $output,
			'local_path' => $local_path,
			'remote_path' => $remote_path,
			'file_name' => $file_name,
			'file_size_bytes' => $file_size,
			'completed_at' => date('Y-m-d H:i:s'),
		));
	}

	protected function refresh_database_metrics($server_id)
	{
		$config = $server_id ? $this->Ssh_config_model->find_active_by_server($server_id, TRUE) : NULL;

		if ( ! $config)
		{
			$config = $this->Ssh_config_model->first_active(TRUE);
		}

		if ( ! $config)
		{
			return FALSE;
		}

		$collected = $this->remote_metric_collector->collect($config);

		if ( ! $collected['ok'])
		{
			return FALSE;
		}

		$preferred_server_id = ! empty($config->server_id) ? (int) $config->server_id : NULL;
		$saved_server_id = $this->Monitoring_model->upsert_server_from_payload($collected['payload'], 'ssh-pull', $preferred_server_id);

		if ( ! $this->Monitoring_model->record_metrics($saved_server_id, $collected['payload'], $config->host))
		{
			return FALSE;
		}

		if (empty($config->server_id) || (int) $config->server_id !== (int) $saved_server_id)
		{
			$this->Ssh_config_model->link_server($config->id, $saved_server_id);
		}

		$this->Ssh_config_model->touch_connected($config->id);

		return TRUE;
	}

	protected function selected_config($config_id = NULL, $server_id = NULL)
	{
		if ($config_id)
		{
			$config = $this->Ssh_config_model->find((int) $config_id, TRUE);
			if ($config)
			{
				return $config;
			}
		}

		if ($server_id)
		{
			$config = $this->Ssh_config_model->find_active_by_server($server_id, TRUE);
			if ($config)
			{
				return $config;
			}
		}

		return $this->Ssh_config_model->first_active(TRUE);
	}

	protected function ensure_backup_dir($path)
	{
		$path = trim((string) $path, '/');

		if ($path === '' || strpos($path, '..') !== FALSE)
		{
			return FALSE;
		}

		$absolute = FCPATH.$path;
		if ( ! is_dir($absolute))
		{
			@mkdir($absolute, 0775, TRUE);
		}

		return is_dir($absolute) && is_writable($absolute) ? $absolute : FALSE;
	}

	protected function safe_local_backup_path($relative_path)
	{
		if ( ! $relative_path || strpos($relative_path, '..') !== FALSE)
		{
			return FALSE;
		}

		$settings = $this->Database_backup_model->settings();
		$base = realpath(FCPATH.trim($settings->storage_path, '/'));
		$target = realpath(FCPATH.$relative_path);

		if ( ! $base || ! $target || strpos($target, $base) !== 0)
		{
			return FALSE;
		}

		return $target;
	}

	protected function output_file_download($absolute, $file_name)
	{
		if (ob_get_level())
		{
			@ob_end_clean();
		}

		header('Content-Type: application/sql');
		header('Content-Disposition: attachment; filename="'.$file_name.'"');
		header('Content-Length: '.filesize($absolute));
		header('Cache-Control: private, max-age=0, must-revalidate');
		readfile($absolute);
		exit;
	}

	protected function database_maintenance_command($action, $target)
	{
		$script = <<<'SH'
ACTION=__ACTION__
TARGET=__TARGET__

find_db_container() {
	if ! command -v docker >/dev/null 2>&1; then
		return 1
	fi
	if [ -n "$TARGET" ] && docker inspect "$TARGET" >/dev/null 2>&1; then
		printf '%s' "$TARGET"
		return 0
	fi
	docker ps --format '{{.Names}} {{.Image}}' 2>/dev/null | awk 'BEGIN{IGNORECASE=1} /mysql|mariadb/ {print $1; exit}'
}

CONTAINER="$(find_db_container)"
if [ -n "$CONTAINER" ]; then
	case "$ACTION" in
		restart)
			docker restart "$CONTAINER"
			;;
		optimize)
			docker exec "$CONTAINER" sh -lc 'mysqlcheck -o --all-databases'
			;;
		repair)
			docker exec "$CONTAINER" sh -lc 'mysqlcheck -r --all-databases'
			;;
	esac
	exit $?
fi

case "$ACTION" in
	restart)
		sudo systemctl restart mysql || sudo systemctl restart mariadb
		;;
	optimize)
		mysqlcheck -o --all-databases
		;;
	repair)
		mysqlcheck -r --all-databases
		;;
esac
SH;

		return 'sh -lc '.remote_arg(strtr($script, array(
			'__ACTION__' => remote_arg($action),
			'__TARGET__' => remote_arg($target),
		)));
	}

	protected function allowed_sql_query($query)
	{
		$action = $this->sql_action($query);

		if ( ! $action)
		{
			return FALSE;
		}

		$role = current_role();
		$read = array('SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN');

		if ($role === 'Super Admin')
		{
			return in_array($action, array_merge($read, array('INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP')), TRUE);
		}

		if ($role === 'Admin')
		{
			return in_array($action, array_merge($read, array('INSERT', 'UPDATE')), TRUE);
		}

		if ($role === 'Operator')
		{
			return in_array($action, $read, TRUE);
		}

		return FALSE;
	}

	protected function sql_action($query)
	{
		$query = preg_replace('/^\s*(--[^\n]*\n|\/\*.*?\*\/\s*)+/s', '', (string) $query);

		return preg_match('/^\s*([a-zA-Z]+)/', $query, $match) ? strtoupper($match[1]) : '';
	}

	protected function query_output_text($result)
	{
		if ( ! empty($result['output']))
		{
			return $result['output'];
		}

		return json_encode($result, JSON_PRETTY_PRINT);
	}

	protected function valid_optional_name($value)
	{
		return $value === '' || preg_match('/^[a-zA-Z0-9_\-\.]+$/', $value);
	}

	protected function valid_required_name($value)
	{
		return $value !== '' && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $value);
	}

	protected function format_bytes($bytes)
	{
		$bytes = (float) $bytes;
		$units = array('B', 'KB', 'MB', 'GB');
		$index = 0;

		while ($bytes >= 1024 && $index < count($units) - 1)
		{
			$bytes = $bytes / 1024;
			$index++;
		}

		return round($bytes, $index === 0 ? 0 : 2).' '.$units[$index];
	}
}
