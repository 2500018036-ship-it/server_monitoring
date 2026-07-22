<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Remote_database
{
	protected $CI;
	protected $last_error = '';

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('Remote_ssh');
		$this->CI->load->helper('remote');
	}

	public function last_error()
	{
		return $this->last_error;
	}

	public function statistics($config, $target = '')
	{
		return $this->execute_json($config, 'statistics', array('target' => $target), 40);
	}

	public function list_databases($config, $target = '')
	{
		return $this->execute_json($config, 'list_databases', array('target' => $target), 40);
	}

	public function list_tables($config, $database, $target = '')
	{
		return $this->execute_json($config, 'list_tables', array('target' => $target, 'database' => $database), 50);
	}

	public function table_detail($config, $database, $table, $target = '')
	{
		return $this->execute_json($config, 'table_detail', array('target' => $target, 'database' => $database, 'table' => $table), 50);
	}

	public function table_data($config, $database, $table, $page = 1, $limit = 100, $search = '', $target = '')
	{
		return $this->execute_json($config, 'table_data', array(
			'target' => $target,
			'database' => $database,
			'table' => $table,
			'page' => max((int) $page, 1),
			'limit' => max(min((int) $limit, 500), 1),
			'search' => $search,
		), 70);
	}

	public function query($config, $query, $database = '', $target = '')
	{
		return $this->execute_json($config, 'query', array(
			'target' => $target,
			'database' => $database,
			'query' => $query,
		), 90);
	}

	public function backup($config, $target, $remote_path, $database = '', $zip = FALSE)
	{
		return $this->execute_json($config, 'backup', array(
			'target' => $target,
			'database' => $database,
			'remote_path' => $remote_path,
			'zip' => (bool) $zip,
		), 300);
	}

	public function restore($config, $remote_path, $database = '', $target = '')
	{
		return $this->execute_json($config, 'restore', array(
			'target' => $target,
			'database' => $database,
			'remote_path' => $remote_path,
		), 300);
	}

	public function download_remote_file($config, $remote_path, $local_path)
	{
		$sftp = $this->CI->remote_ssh->sftp($config);

		if ( ! $sftp)
		{
			$this->last_error = $this->CI->remote_ssh->last_error();
			return FALSE;
		}

		try
		{
			return (bool) $sftp->get($remote_path, $local_path);
		}
		catch (Exception $e)
		{
			$this->last_error = $e->getMessage();
			return FALSE;
		}
	}

	public function upload_local_file($config, $local_path, $remote_path)
	{
		$sftp = $this->CI->remote_ssh->sftp($config);

		if ( ! $sftp)
		{
			$this->last_error = $this->CI->remote_ssh->last_error();
			return FALSE;
		}

		try
		{
			return (bool) $sftp->put($remote_path, $local_path, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE);
		}
		catch (Exception $e)
		{
			$this->last_error = $e->getMessage();
			return FALSE;
		}
	}

	protected function execute_json($config, $operation, $params = array(), $timeout = 60)
	{
		$params['operation'] = $operation;
		$script = str_replace('__PARAMS_B64__', base64_encode(json_encode($params)), $this->python_script());
		$command = "python3 - <<'PY'\n".$script."\nPY";
		$result = $this->CI->remote_ssh->execute($config, $command, $timeout);

		if ( ! $result['ok'])
		{
			$this->last_error = $result['output'];
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		$payload = $this->decode_json_output($result['output']);

		if ( ! is_array($payload))
		{
			$this->last_error = 'Output remote database tidak valid: '.substr(trim($result['output']), 0, 800);
			return array('ok' => FALSE, 'message' => $this->last_error, 'raw' => $result['output']);
		}

		if (empty($payload['ok']))
		{
			$this->last_error = isset($payload['message']) ? $payload['message'] : 'Remote database operation failed.';
		}

		return $payload;
	}

	protected function decode_json_output($output)
	{
		$output = trim((string) $output);
		$payload = json_decode($output, TRUE);

		if (is_array($payload))
		{
			return $payload;
		}

		$lines = preg_split('/\r\n|\r|\n/', $output);
		for ($i = count($lines) - 1; $i >= 0; $i--)
		{
			$line = trim($lines[$i]);
			if ($line === '' || substr($line, 0, 1) !== '{')
			{
				continue;
			}

			$payload = json_decode($line, TRUE);
			if (is_array($payload))
			{
				return $payload;
			}
		}

		return NULL;
	}

	protected function python_script()
	{
		return <<<'PY'
import base64
import csv
import json
import os
import re
import subprocess
import sys
import zipfile
from io import StringIO

PARAMS = json.loads(base64.b64decode("__PARAMS_B64__").decode("utf-8"))
DB_KEYWORDS = ["mysql", "mariadb", "postgres", "postgresql", "mongo", "redis", "database", "_db", "-db"]


def emit(payload):
	print(json.dumps(payload, default=str))


def fail(message, extra=None):
	payload = {"ok": False, "message": message}
	if extra:
		payload.update(extra)
	emit(payload)
	sys.exit(0)


def command(args, input_data=None, timeout=30, text=True, stdout_file=None):
	try:
		if stdout_file:
			with open(stdout_file, "wb") as handle:
				proc = subprocess.run(args, input=input_data, stdout=handle, stderr=subprocess.PIPE, timeout=timeout)
			err = proc.stderr.decode("utf-8", "ignore") if isinstance(proc.stderr, bytes) else (proc.stderr or "")
			return proc.returncode, "", err

		proc = subprocess.run(args, input=input_data, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout, text=text)
		return proc.returncode, proc.stdout or "", proc.stderr or ""
	except Exception as error:
		return 1, "", str(error)


def docker_available():
	code, out, err = command(["which", "docker"], timeout=3)
	return code == 0 and bool(out.strip())


def docker_rows():
	if not docker_available():
		return []

	code, out, err = command(["docker", "ps", "-a", "--format", "{{json .}}"], timeout=8)
	rows = []

	for line in out.splitlines():
		try:
			rows.append(json.loads(line))
		except Exception:
			pass

	return rows


def is_db_container(row):
	haystack = ((row.get("Names") or "") + " " + (row.get("Image") or "")).lower()
	return any(keyword in haystack for keyword in DB_KEYWORDS)


def inspect_env(container):
	code, out, err = command(["docker", "inspect", "--format", "{{json .Config.Env}}", container], timeout=8)
	env = {}

	try:
		rows = json.loads(out)
	except Exception:
		rows = []

	for item in rows or []:
		if "=" in item:
			key, value = item.split("=", 1)
			env[key] = value

	return env


def docker_file_value(container, path):
	if not path:
		return ""
	code, out, err = command(["docker", "exec", container, "cat", path], timeout=5)
	return out.strip() if code == 0 else ""


def first_env(env, keys):
	for key in keys:
		if env.get(key):
			return env.get(key)
	return ""


def env_or_file(container, env, keys):
	value = first_env(env, keys)
	if value:
		return value
	file_path = first_env(env, [key + "_FILE" for key in keys])
	return docker_file_value(container, file_path)


def choose_container(target=""):
	rows = docker_rows()
	if target:
		for row in rows:
			if target in (row.get("Names"), row.get("ID")):
				return row
		code, out, err = command(["docker", "inspect", target], timeout=5)
		if code == 0:
			return {"Names": target, "Image": ""}

	for row in rows:
		if is_db_container(row):
			return row

	return None


def engine_from_row(row):
	haystack = ((row.get("Names") or "") + " " + (row.get("Image") or "")).lower()
	if "mariadb" in haystack:
		return "mariadb"
	if "mysql" in haystack:
		return "mysql"
	if "postgres" in haystack:
		return "postgresql"
	if "mongo" in haystack:
		return "mongodb"
	if "redis" in haystack:
		return "redis"
	return "mysql"


def mysql_context():
	target = PARAMS.get("target") or ""
	row = choose_container(target)

	if row:
		container = row.get("Names") or row.get("ID") or target
		engine = engine_from_row(row)
		if engine not in ("mysql", "mariadb"):
			fail("Database engine " + engine + " belum didukung untuk explorer/query.")

		env = inspect_env(container)
		root_password = env_or_file(container, env, ["MARIADB_ROOT_PASSWORD", "MYSQL_ROOT_PASSWORD"])
		user = "root" if root_password else first_env(env, ["MARIADB_USER", "MYSQL_USER"]) or "root"
		password = root_password or env_or_file(container, env, ["MARIADB_PASSWORD", "MYSQL_PASSWORD"])

		return {
			"mode": "docker",
			"container": container,
			"user": user,
			"password": password,
			"engine": engine,
			"status": row.get("Status") or "",
		}

	return {"mode": "host", "container": "", "user": "root", "password": "", "engine": "mysql", "status": ""}


def mysql_base_args(ctx, binary="mysql"):
	if ctx["mode"] == "docker":
		args = ["docker", "exec"]
		if ctx.get("password"):
			args += ["-e", "MYSQL_PWD=" + ctx["password"]]
		args += [ctx["container"], binary, "-u" + ctx["user"]]
		return args

	args = [binary, "-u" + ctx["user"]]
	return args


def run_mysql(ctx, sql, database="", skip_column_names=False, timeout=40):
	args = mysql_base_args(ctx, "mysql") + ["--batch", "--raw", "--default-character-set=utf8mb4"]
	if skip_column_names:
		args.append("-N")
	if database:
		args += ["-D", database]
	args += ["-e", sql]

	if ctx["mode"] == "host" and ctx.get("password"):
		env = os.environ.copy()
		env["MYSQL_PWD"] = ctx["password"]
		proc = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout, text=True, env=env)
		return proc.returncode, proc.stdout or "", proc.stderr or ""

	return command(args, timeout=timeout)


def run_mysqladmin(ctx, args, timeout=30):
	cmd = mysql_base_args(ctx, "mysqladmin") + args
	return command(cmd, timeout=timeout)


def sql_literal(value):
	return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"


def ident(value):
	return "`" + str(value).replace("`", "``") + "`"


def parse_tsv(text):
	text = text or ""
	if not text.strip():
		return [], []

	reader = csv.reader(StringIO(text), delimiter="\t")
	rows = list(reader)
	if not rows:
		return [], []

	columns = rows[0]
	items = []
	for row in rows[1:]:
		item = {}
		for index, column in enumerate(columns):
			item[column] = row[index] if index < len(row) else ""
		items.append(item)

	return columns, items


def parse_number(value):
	try:
		if value is None or value == "":
			return None
		return float(str(value).strip())
	except Exception:
		return None


def first_scalar(sql, database=""):
	ctx = mysql_context()
	code, out, err = run_mysql(ctx, sql, database, True)
	if code != 0:
		return None
	line = out.strip().splitlines()
	return line[-1].strip() if line else None


def op_statistics():
	ctx = mysql_context()
	code, version_out, version_err = run_mysql(ctx, "SELECT VERSION(), @@version_comment;", "", True)
	if code != 0:
		fail(version_err or "Tidak bisa membaca versi database.")

	version_parts = version_out.strip().split("\t")
	version = version_parts[0] if version_parts else ""
	comment = version_parts[1] if len(version_parts) > 1 else ""
	stats_sql = """
SELECT
	(SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME NOT IN ('information_schema','mysql','performance_schema','sys')) AS total_database,
	(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA NOT IN ('information_schema','mysql','performance_schema','sys')) AS total_table,
	(SELECT COALESCE(SUM(TABLE_ROWS),0) FROM information_schema.TABLES WHERE TABLE_SCHEMA NOT IN ('information_schema','mysql','performance_schema','sys')) AS total_record,
	(SELECT ROUND(COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH),0)/1024/1024,2) FROM information_schema.TABLES WHERE TABLE_SCHEMA NOT IN ('information_schema','mysql','performance_schema','sys')) AS total_size_mb
"""
	code, out, err = run_mysql(ctx, stats_sql, "", False)
	if code != 0:
		fail(err or "Tidak bisa membaca statistik database.")

	columns, rows = parse_tsv(out)
	base = rows[0] if rows else {}
	status_map = {}
	code, out, err = run_mysql(ctx, "SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Max_used_connections','Slow_queries');", "", False)
	if code == 0:
		for row in parse_tsv(out)[1]:
			status_map[row.get("Variable_name")] = row.get("Value")

	max_connections = first_scalar("SHOW VARIABLES LIKE 'max_connections';")
	if max_connections and "\t" in max_connections:
		max_connections = max_connections.split("\t")[-1]

	emit({
		"ok": True,
		"engine": ctx["engine"],
		"container": ctx.get("container"),
		"version": version,
		"version_comment": comment,
		"mariadb_version": version if "mariadb" in (version + " " + comment).lower() else "",
		"mysql_version": version if "mariadb" not in (version + " " + comment).lower() else "",
		"total_database": int(float(base.get("total_database") or 0)),
		"total_table": int(float(base.get("total_table") or 0)),
		"total_record": int(float(base.get("total_record") or 0)),
		"total_size_mb": parse_number(base.get("total_size_mb")) or 0,
		"uptime_seconds": int(float(status_map.get("Uptime") or 0)),
		"active_connection": int(float(status_map.get("Threads_connected") or 0)),
		"max_connection": int(float(max_connections or 0)),
		"slow_query_count": int(float(status_map.get("Slow_queries") or 0)),
		"status": "online",
		"connection_status": ("Docker container " + ctx.get("container") + ": " + ctx.get("status")) if ctx.get("container") else "Host MySQL/MariaDB",
	})


def op_list_databases():
	ctx = mysql_context()
	code, out, err = run_mysql(ctx, "SHOW DATABASES;", "", False)
	if code != 0:
		fail(err or "Tidak bisa membaca database.")
	columns, rows = parse_tsv(out)
	names = []
	for row in rows:
		names.append(list(row.values())[0])
	emit({"ok": True, "databases": names})


def op_list_tables():
	database = PARAMS.get("database") or ""
	if not database:
		fail("Database wajib dipilih.")
	ctx = mysql_context()
	sql = """
SELECT TABLE_NAME AS table_name,
	COALESCE(TABLE_ROWS,0) AS records,
	ENGINE AS engine,
	ROUND((COALESCE(DATA_LENGTH,0) + COALESCE(INDEX_LENGTH,0))/1024/1024,2) AS size_mb,
	TABLE_COLLATION AS collation_name,
	CREATE_TIME AS created_time,
	UPDATE_TIME AS updated_time
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = %s
ORDER BY TABLE_NAME
""" % sql_literal(database)
	code, out, err = run_mysql(ctx, sql, "", False)
	if code != 0:
		fail(err or "Tidak bisa membaca tabel.")
	columns, rows = parse_tsv(out)
	emit({"ok": True, "database": database, "tables": rows})


def op_table_detail():
	database = PARAMS.get("database") or ""
	table = PARAMS.get("table") or ""
	if not database or not table:
		fail("Database dan table wajib dipilih.")
	ctx = mysql_context()
	sql = """
SELECT c.COLUMN_NAME AS column_name,
	c.COLUMN_TYPE AS column_type,
	c.DATA_TYPE AS data_type,
	c.CHARACTER_MAXIMUM_LENGTH AS length_value,
	c.COLUMN_KEY AS column_key,
	kcu.REFERENCED_TABLE_NAME AS referenced_table,
	kcu.REFERENCED_COLUMN_NAME AS referenced_column,
	c.COLUMN_DEFAULT AS default_value,
	c.IS_NULLABLE AS nullable,
	c.EXTRA AS extra
FROM information_schema.COLUMNS c
LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu
	ON kcu.TABLE_SCHEMA = c.TABLE_SCHEMA
	AND kcu.TABLE_NAME = c.TABLE_NAME
	AND kcu.COLUMN_NAME = c.COLUMN_NAME
	AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
WHERE c.TABLE_SCHEMA = %s AND c.TABLE_NAME = %s
ORDER BY c.ORDINAL_POSITION
""" % (sql_literal(database), sql_literal(table))
	code, out, err = run_mysql(ctx, sql, "", False)
	if code != 0:
		fail(err or "Tidak bisa membaca struktur tabel.")
	columns, rows = parse_tsv(out)
	emit({"ok": True, "database": database, "table": table, "columns": rows})


def op_table_data():
	database = PARAMS.get("database") or ""
	table = PARAMS.get("table") or ""
	page = max(int(PARAMS.get("page") or 1), 1)
	limit = min(max(int(PARAMS.get("limit") or 100), 1), 500)
	search = PARAMS.get("search") or ""
	offset = (page - 1) * limit
	if not database or not table:
		fail("Database dan table wajib dipilih.")

	ctx = mysql_context()
	code, columns_out, err = run_mysql(ctx, "SHOW COLUMNS FROM " + ident(table), database, False)
	if code != 0:
		fail(err or "Tidak bisa membaca kolom tabel.")
	column_rows = parse_tsv(columns_out)[1]
	column_names = [row.get("Field") for row in column_rows if row.get("Field")]
	where = ""
	if search and column_names:
		parts = ["CAST(%s AS CHAR) LIKE %s" % (ident(column), sql_literal("%" + search + "%")) for column in column_names]
		where = " WHERE " + " OR ".join(parts)
	count_sql = "SELECT COUNT(*) FROM " + ident(table) + where
	total = first_scalar(count_sql, database)
	select_sql = "SELECT * FROM " + ident(table) + where + " LIMIT %d OFFSET %d" % (limit, offset)
	code, out, err = run_mysql(ctx, select_sql, database, False, 60)
	if code != 0:
		fail(err or "Tidak bisa membaca isi tabel.")
	columns, rows = parse_tsv(out)
	emit({
		"ok": True,
		"database": database,
		"table": table,
		"page": page,
		"limit": limit,
		"total": int(float(total or 0)),
		"columns": columns,
		"rows": rows,
	})


def op_query():
	query = (PARAMS.get("query") or "").strip()
	database = PARAMS.get("database") or ""
	if not query:
		fail("Query tidak boleh kosong.")
	ctx = mysql_context()
	code, out, err = run_mysql(ctx, query, database, False, 90)
	if code != 0:
		fail(err or "Query gagal dijalankan.", {"output": out})
	columns, rows = parse_tsv(out)
	emit({"ok": True, "columns": columns, "rows": rows, "output": out.strip(), "affected_message": "Query berhasil dijalankan."})


def op_backup():
	ctx = mysql_context()
	target = PARAMS.get("target") or ""
	database = PARAMS.get("database") or ""
	if not database and target and target != ctx.get("container"):
		database = target
	remote_path = PARAMS.get("remote_path") or "/tmp/database_backup.sql"
	do_zip = bool(PARAMS.get("zip"))
	os.makedirs(os.path.dirname(remote_path) or "/tmp", exist_ok=True)
	dump_path = remote_path
	if do_zip and remote_path.endswith(".zip"):
		dump_path = remote_path[:-4] + ".sql"

	args = mysql_base_args(ctx, "mysqldump") + ["--single-transaction", "--routines", "--events", "--default-character-set=utf8mb4"]
	if database:
		args.append(database)
	else:
		args.append("--all-databases")
	code, out, err = command(args, timeout=300, stdout_file=dump_path)
	if code != 0:
		fail(err or "Backup gagal dibuat.")

	final_path = dump_path
	if do_zip:
		final_path = remote_path
		with zipfile.ZipFile(final_path, "w", zipfile.ZIP_DEFLATED) as archive:
			archive.write(dump_path, os.path.basename(dump_path))
		try:
			os.remove(dump_path)
		except Exception:
			pass

	emit({
		"ok": True,
		"remote_path": final_path,
		"file_name": os.path.basename(final_path),
		"file_size_bytes": os.path.getsize(final_path),
		"database": database or "all-databases",
		"message": "Backup berhasil dibuat.",
	})


def op_restore():
	ctx = mysql_context()
	target = PARAMS.get("target") or ""
	database = PARAMS.get("database") or ""
	if not database and target and target != ctx.get("container"):
		database = target
	remote_path = PARAMS.get("remote_path") or ""
	if not remote_path or not os.path.exists(remote_path):
		fail("File restore tidak ditemukan di server remote.")
	restore_path = remote_path
	if remote_path.endswith(".zip"):
		with zipfile.ZipFile(remote_path, "r") as archive:
			sql_files = [name for name in archive.namelist() if name.endswith(".sql")]
			if not sql_files:
				fail("File ZIP tidak berisi SQL.")
			restore_path = "/tmp/restore_" + os.path.basename(sql_files[0])
			with open(restore_path, "wb") as handle:
				handle.write(archive.read(sql_files[0]))
	with open(restore_path, "rb") as handle:
		data = handle.read()
	args = mysql_base_args(ctx, "mysql")
	if database:
		args.append(database)
	code, out, err = command(args, input_data=data, timeout=300, text=False)
	if code != 0:
		fail(err or "Restore gagal dijalankan.")
	emit({"ok": True, "message": "Restore berhasil dijalankan.", "output": out})


operation = PARAMS.get("operation")
try:
	if operation == "statistics":
		op_statistics()
	elif operation == "list_databases":
		op_list_databases()
	elif operation == "list_tables":
		op_list_tables()
	elif operation == "table_detail":
		op_table_detail()
	elif operation == "table_data":
		op_table_data()
	elif operation == "query":
		op_query()
	elif operation == "backup":
		op_backup()
	elif operation == "restore":
		op_restore()
	else:
		fail("Operation tidak dikenal.")
except Exception as error:
	fail(str(error))
PY;
	}
}
