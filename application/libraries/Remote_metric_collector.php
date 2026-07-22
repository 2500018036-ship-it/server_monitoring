<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Remote_metric_collector
{
	protected $CI;
	protected $last_error = '';

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('Remote_ssh');
	}

	public function last_error()
	{
		return $this->last_error;
	}

	public function collect($config)
	{
		$started = microtime(TRUE);
		$result = $this->CI->remote_ssh->execute($config, $this->collector_command(), 30);

		if ( ! $result['ok'])
		{
			$this->last_error = $result['output'];
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		$payload = json_decode(trim($result['output']), TRUE);

		if ( ! is_array($payload))
		{
			$this->last_error = 'Output monitoring dari SSH bukan JSON valid: '.substr(trim($result['output']), 0, 500);
			return array('ok' => FALSE, 'message' => $this->last_error);
		}

		$duration = (int) ((microtime(TRUE) - $started) * 1000);
		$payload['response_time_ms'] = $duration;
		$payload['latency_ms'] = isset($result['duration_ms']) ? (int) $result['duration_ms'] : $duration;
		$payload = $this->enrich_payload($payload, $config);

		return array('ok' => TRUE, 'payload' => $payload, 'duration_ms' => $duration);
	}

	protected function enrich_payload($payload, $config)
	{
		$server = isset($payload['server']) && is_array($payload['server']) ? $payload['server'] : array();
		$hostname = isset($server['hostname']) && $server['hostname'] !== '' ? $server['hostname'] : $config->host;
		$stable_agent_id = isset($config->id) && (int) $config->id > 0
			? 'ssh-config-'.(int) $config->id
			: 'ssh-'.strtolower(preg_replace('/[^a-zA-Z0-9_\-\.]/', '-', $hostname));

		$payload['agent_id'] = $stable_agent_id;

		$server['agent_id'] = $payload['agent_id'];
		$server['server_name'] = isset($config->name) && trim($config->name) !== '' ? $config->name : (isset($server['server_name']) && $server['server_name'] !== '' ? $server['server_name'] : $hostname);
		$server['hostname'] = $hostname;
		$server['public_ip'] = isset($server['public_ip']) && $server['public_ip'] !== '' ? $server['public_ip'] : $config->host;
		$server['provider'] = isset($server['provider']) && $server['provider'] !== '' ? $server['provider'] : 'SSH Pull';

		$payload['server'] = $server;

		return $payload;
	}

	protected function collector_command()
	{
		return <<<'SH'
python3 - <<'PY'
import datetime
import json
import os
import platform
import re
import shutil
import socket
import subprocess
import ssl
import time
import urllib.error
import urllib.request

SERVICES = ["nginx", "apache2", "httpd", "php-fpm", "mysql", "mariadb", "docker", "ssh", "sshd", "cron", "crond"]
DOCKER_AVAILABLE = None
DOCKER_ROWS = None
PROCESS_ROWS = None


def now_string():
	return datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def command(args, timeout=3):
	try:
		return subprocess.check_output(args, stderr=subprocess.DEVNULL, text=True, timeout=timeout).strip()
	except Exception:
		return ""


def command_combined(args, timeout=3):
	try:
		return subprocess.check_output(args, stderr=subprocess.STDOUT, text=True, timeout=timeout).strip()
	except Exception:
		return ""


def get_url(url, timeout=2, headers=None):
	try:
		request = urllib.request.Request(url, headers=headers or {})
		with urllib.request.urlopen(request, timeout=timeout) as response:
			return response.read().decode("utf-8", "ignore").strip()
	except Exception:
		return ""


def azure_metadata(path):
	return get_url(
		"http://169.254.169.254/metadata/instance" + path + "?api-version=2021-02-01&format=text",
		timeout=1,
		headers={"Metadata": "true"},
	)


def private_ip():
	sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
	try:
		sock.connect(("8.8.8.8", 80))
		return sock.getsockname()[0]
	except Exception:
		return ""
	finally:
		sock.close()


def uptime_seconds():
	try:
		with open("/proc/uptime", "r", encoding="utf-8") as handle:
			return int(float(handle.read().split()[0]))
	except Exception:
		return 0


def meminfo():
	data = {}
	try:
		with open("/proc/meminfo", "r", encoding="utf-8") as handle:
			for line in handle:
				key, value = line.split(":", 1)
				data[key] = int(value.strip().split()[0])
	except Exception:
		pass
	return data


def bytes_to_mb(value):
	return int(value / 1024 / 1024)


def kb_to_mb(value):
	return int(value / 1024)


def cpu_times():
	try:
		with open("/proc/stat", "r", encoding="utf-8") as handle:
			values = [int(item) for item in handle.readline().split()[1:]]
		idle = values[3] + (values[4] if len(values) > 4 else 0)
		return idle, sum(values)
	except Exception:
		return 0, 0


def cpu_usage():
	idle_a, total_a = cpu_times()
	time.sleep(0.25)
	idle_b, total_b = cpu_times()
	total_delta = total_b - total_a
	idle_delta = idle_b - idle_a
	if total_delta <= 0:
		return 0
	return round((1 - (idle_delta / float(total_delta))) * 100, 2)


def cpu_model():
	try:
		with open("/proc/cpuinfo", "r", encoding="utf-8") as handle:
			for line in handle:
				if line.lower().startswith("model name"):
					return line.split(":", 1)[1].strip()
	except Exception:
		pass
	return platform.processor()


def process_rows():
	global PROCESS_ROWS
	if PROCESS_ROWS is not None:
		return PROCESS_ROWS

	output = command(["ps", "-eo", "pid,user,comm,%cpu,%mem,etimes"], timeout=4)
	items = []
	for line in output.splitlines()[1:]:
		parts = line.split(None, 5)
		if len(parts) < 6:
			continue
		items.append({
			"pid": int(parts[0]) if parts[0].isdigit() else None,
			"user": parts[1],
			"command": parts[2],
			"cpu": float(parts[3]) if parts[3].replace(".", "", 1).isdigit() else 0,
			"ram": float(parts[4]) if parts[4].replace(".", "", 1).isdigit() else 0,
			"running_time": parts[5] + "s",
		})
	PROCESS_ROWS = items
	return PROCESS_ROWS


def top_processes(sort_key):
	key = "ram" if sort_key == "memory" else "cpu"
	return sorted(process_rows(), key=lambda item: item.get(key) or 0, reverse=True)[:20]


def memory_payload():
	info = meminfo()
	total = info.get("MemTotal", 0)
	available = info.get("MemAvailable", info.get("MemFree", 0))
	used = max(total - available, 0)
	swap_total = info.get("SwapTotal", 0)
	swap_free = info.get("SwapFree", 0)
	usage = round((used / float(total)) * 100, 2) if total else 0

	return {
		"total_mb": kb_to_mb(total),
		"used_mb": kb_to_mb(used),
		"free_mb": kb_to_mb(available),
		"cache_mb": kb_to_mb(info.get("Cached", 0)),
		"buffer_mb": kb_to_mb(info.get("Buffers", 0)),
		"swap_used_mb": kb_to_mb(max(swap_total - swap_free, 0)),
		"swap_free_mb": kb_to_mb(swap_free),
		"usage_percent": usage,
		"top_processes": top_processes("memory"),
	}


def storage_payload():
	usage = shutil.disk_usage("/")
	percent = round((usage.used / float(usage.total)) * 100, 2) if usage.total else 0
	root = {
		"mount_point": "/",
		"disk_total_gb": round(usage.total / 1024 / 1024 / 1024, 2),
		"disk_used_gb": round(usage.used / 1024 / 1024 / 1024, 2),
		"disk_free_gb": round(usage.free / 1024 / 1024 / 1024, 2),
		"disk_percentage": percent,
		"disk_read_speed": 0,
		"disk_write_speed": 0,
		"iops": 0,
	}
	root["disks"] = [root.copy()]
	return root


def active_connections():
	total = 0
	for path in ("/proc/net/tcp", "/proc/net/tcp6"):
		try:
			with open(path, "r", encoding="utf-8") as handle:
				total += max(len(handle.readlines()) - 1, 0)
		except Exception:
			pass
	return total


def network_payload():
	try:
		with open("/proc/net/dev", "r", encoding="utf-8") as handle:
			for line in handle.readlines()[2:]:
				name, values = line.split(":", 1)
				name = name.strip()
				if name == "lo":
					continue
				parts = values.split()
				return {
					"interface_name": name,
					"upload_speed": 0,
					"download_speed": 0,
					"total_upload": int(parts[8]),
					"total_download": int(parts[0]),
					"packet_sent": int(parts[9]),
					"packet_received": int(parts[1]),
					"packet_loss": 0,
					"active_connections": active_connections(),
				}
	except Exception:
		pass
	return {"upload_speed": 0, "download_speed": 0, "active_connections": active_connections()}


def service_payload():
	items = []
	if not shutil.which("systemctl"):
		return items

	output = command(["systemctl", "list-units", "--type=service", "--all", "--no-legend", "--no-pager"], timeout=5)
	if not output:
		return items

	wanted = set(SERVICES)
	for line in output.splitlines():
		parts = line.split(None, 4)
		if len(parts) < 4:
			continue
		unit = parts[0].replace(".service", "")
		if unit not in wanted:
			continue
		active_state = parts[2]
		sub_state = parts[3]
		items.append({
			"name": unit,
			"status": "running" if active_state == "active" else "stopped",
			"log_excerpt": sub_state,
		})
	return items


def docker_payload():
	if not docker_available():
		return {"available": False, "containers": []}
	containers = []
	for raw in docker_rows():
		containers.append({
			"container_id": raw.get("ID"),
			"container_name": raw.get("Names"),
			"image": raw.get("Image"),
			"status": raw.get("Status"),
			"cpu": 0,
			"ram": "",
			"restart_count": 0,
			"ports": raw.get("Ports"),
			"network": "",
			"volume": "",
		})
	return {"available": True, "containers": containers}


def docker_available():
	global DOCKER_AVAILABLE
	if DOCKER_AVAILABLE is None:
		DOCKER_AVAILABLE = shutil.which("docker") is not None
	return DOCKER_AVAILABLE


def docker_rows():
	global DOCKER_ROWS
	if not docker_available():
		return []
	if DOCKER_ROWS is not None:
		return DOCKER_ROWS

	output = command(["docker", "ps", "-a", "--format", "{{json .}}"], timeout=5)
	rows = []
	for line in output.splitlines():
		try:
			rows.append(json.loads(line))
		except Exception:
			continue
	DOCKER_ROWS = rows
	return DOCKER_ROWS


def parse_docker_ports(raw):
	ports = []
	text = raw.get("Ports") or ""
	for match in re.finditer(r"(?:(?:0\.0\.0\.0|\[?:::\]?|127\.0\.0\.1):)?(\d+)->(\d+)/(tcp|udp)", text):
		try:
			host_port = int(match.group(1))
			target_port = int(match.group(2))
		except Exception:
			continue
		port = {
			"host_port": host_port,
			"target_port": target_port,
			"protocol": match.group(3),
		}
		if port not in ports:
			ports.append(port)
	return ports


def is_database_container(raw):
	haystack = ((raw.get("Names") or "") + " " + (raw.get("Image") or "")).lower()
	keywords = ["mysql", "mariadb", "postgres", "postgresql", "mongo", "redis", "database", "_db", "-db"]

	return any(keyword in haystack for keyword in keywords)


def is_probably_web_container(raw, host_port, target_port):
	web_ports = set([80, 81, 443, 8000, 8008, 8080, 8081, 8443, 9000, 9443, 19999, 3000, 3001])

	if host_port in web_ports or target_port in web_ports:
		return True

	haystack = ((raw.get("Names") or "") + " " + (raw.get("Image") or "")).lower()
	keywords = ["nginx", "apache", "httpd", "caddy", "traefik", "portainer", "netdata", "kuma", "website", "web", "app", "frontend", "laravel", "php", "node", "proxy"]

	return any(keyword in haystack for keyword in keywords)


def http_check(url):
	start = time.time()
	status_code = None
	status = "offline"
	context = ssl._create_unverified_context() if url.startswith("https://") else None
	request = urllib.request.Request(url, headers={"User-Agent": "ServerMonitoringSSHPull/1.0"})

	try:
		with urllib.request.urlopen(request, timeout=4, context=context) as response:
			status_code = response.status
			status = "online" if status_code < 500 else "offline"
	except urllib.error.HTTPError as error:
		status_code = error.code
		status = "online" if status_code < 500 else "offline"
	except Exception:
		status = "offline"

	return {
		"domain": url,
		"status": status,
		"http_status": status_code,
		"response_time_ms": int((time.time() - start) * 1000),
		"ssl_expired_at": None,
		"ping_ms": None,
		"dns_resolve_ms": None,
		"last_check": now_string(),
	}


def website_payload():
	items = []
	seen = set()
	skip_ports = set([22, 25, 110, 143, 465, 587, 993, 995, 3306, 3307, 5432, 6379, 27017, 27018, 27019])

	for raw in docker_rows():
		name = raw.get("Names") or raw.get("Image") or "container"
		if is_database_container(raw):
			continue

		for port_map in parse_docker_ports(raw):
			port = port_map["host_port"]
			target_port = port_map["target_port"]

			if port in skip_ports or target_port in skip_ports or port in seen:
				continue

			if not is_probably_web_container(raw, port, target_port):
				continue

			seen.add(port)
			schemes = ["https", "http"] if port in (443, 8443, 9443) else ["http", "https"]
			first_result = None

			for scheme in schemes:
				local_url = scheme + "://127.0.0.1:" + str(port)
				result = http_check(local_url)
				if first_result is None:
					first_result = result
				if result["status"] == "online":
					display_host = public_ip if public_ip else "127.0.0.1"
					result["domain"] = name + " (" + scheme + "://" + display_host + ":" + str(port) + ")"
					items.append(result)
					break
			else:
				if first_result:
					display_host = public_ip if public_ip else "127.0.0.1"
					first_result["domain"] = name + " (" + first_result["domain"].replace("127.0.0.1", display_host) + ")"
					items.append(first_result)

	for port in (80, 443):
		if port in seen:
			continue
		scheme = "https" if port == 443 else "http"
		result = http_check(scheme + "://127.0.0.1:" + str(port))
		if result["status"] == "online":
			display_host = public_ip if public_ip else "127.0.0.1"
			result["domain"] = scheme + "://" + display_host
			items.append(result)

	return items


def parse_status_number(text, label):
	if label not in text:
		return None
	try:
		return float(text.split(label, 1)[1].strip().split()[0])
	except Exception:
		return None


def parse_first_number(text):
	try:
		cleaned = (text or "").strip()
		return float(cleaned.splitlines()[-1].strip())
	except Exception:
		return None


def parse_status_value(text, key):
	for line in (text or "").splitlines():
		parts = line.split()
		if len(parts) >= 2 and parts[0] == key:
			try:
				return float(parts[-1])
			except Exception:
				return None
	return None


def docker_container_name(raw):
	return raw.get("Names") or raw.get("ID") or ""


def docker_env(container):
	output = command(["docker", "inspect", "--format", "{{json .Config.Env}}", container], timeout=5)
	values = {}

	try:
		rows = json.loads(output)
	except Exception:
		rows = []

	for item in rows or []:
		if "=" not in item:
			continue
		key, value = item.split("=", 1)
		values[key] = value

	return values


def docker_file_value(container, path):
	if not path:
		return ""

	return command(["docker", "exec", container, "cat", path], timeout=3).strip()


def env_first(env, keys):
	for key in keys:
		value = env.get(key)
		if value:
			return value
	return ""


def env_or_file(container, env, keys):
	value = env_first(env, keys)
	if value:
		return value

	file_value = env_first(env, [key + "_FILE" for key in keys])
	return docker_file_value(container, file_value)


def docker_mysql_command(container, args, user="root", password=""):
	base = ["docker", "exec"]

	if password:
		base += ["-e", "MYSQL_PWD=" + password]

	return command(base + [container] + args, timeout=8)


def docker_mysql_stats(raw, engine):
	container = docker_container_name(raw)
	if not container:
		return None

	env = docker_env(container)
	root_password = env_or_file(container, env, ["MARIADB_ROOT_PASSWORD", "MYSQL_ROOT_PASSWORD"])
	user = "root" if root_password else env_first(env, ["MARIADB_USER", "MYSQL_USER"]) or "root"
	password = root_password or env_or_file(container, env, ["MARIADB_PASSWORD", "MYSQL_PASSWORD"])
	status = docker_mysql_command(container, ["mysqladmin", "-u" + user, "status"], user, password)

	if not status:
		ping = docker_mysql_command(container, ["mysqladmin", "-u" + user, "ping"], user, password)
		if not ping:
			return None
		status = ping

	size_query = (
		"SELECT ROUND(COALESCE(SUM(data_length + index_length),0)/1024/1024,2) "
		"FROM information_schema.tables "
		"WHERE table_schema NOT IN ('information_schema','mysql','performance_schema','sys');"
	)
	running_query = "SHOW GLOBAL STATUS LIKE 'Threads_running';"
	stats_output = docker_mysql_command(container, ["mysql", "-u" + user, "-N", "-e", size_query + " " + running_query], user, password)
	stats_lines = [line.strip() for line in stats_output.splitlines() if line.strip()]
	status_text = raw.get("Status") or ""

	return {
		"engine": engine,
		"status": "online" if status_text.lower().startswith("up") else "offline",
		"connection_status": "Docker container " + container + ": " + status_text,
		"database_size_mb": parse_first_number(stats_lines[0]) if stats_lines else None,
		"slow_queries": parse_status_number(status, "Slow queries:"),
		"running_queries": parse_status_value(stats_output, "Threads_running"),
		"threads": parse_status_number(status, "Threads:"),
		"uptime_seconds": parse_status_number(status, "Uptime:"),
		"last_backup": None,
	}


def database_payload():
	if shutil.which("mysqladmin"):
		ping = command(["mysqladmin", "ping"], timeout=3)
		status = command(["mysqladmin", "status"], timeout=3)
		if ping or status:
			return {
				"engine": "mysql",
				"status": "online" if "alive" in ping.lower() else "unknown",
				"connection_status": ping or status,
				"database_size_mb": None,
				"slow_queries": parse_status_number(status, "Slow queries:"),
				"running_queries": parse_status_number(status, "Queries per second avg:"),
				"threads": parse_status_number(status, "Threads:"),
				"uptime_seconds": parse_status_number(status, "Uptime:"),
				"last_backup": None,
			}

	if shutil.which("pg_isready"):
		status = command(["pg_isready"], timeout=3)
		if status:
			return {
				"engine": "postgresql",
				"status": "online" if "accepting connections" in status.lower() else "offline",
				"connection_status": status,
				"database_size_mb": None,
				"slow_queries": None,
				"running_queries": None,
				"threads": None,
				"uptime_seconds": None,
				"last_backup": None,
			}

	engines = [
		("mariadb", ["mariadb"]),
		("mysql", ["mysql"]),
		("postgresql", ["postgres", "postgresql"]),
		("mongodb", ["mongo"]),
		("redis", ["redis"]),
	]

	for raw in docker_rows():
		haystack = ((raw.get("Names") or "") + " " + (raw.get("Image") or "")).lower()
		for engine, keywords in engines:
			if not any(keyword in haystack for keyword in keywords):
				continue

			status_text = raw.get("Status") or ""
			if engine in ("mysql", "mariadb") and status_text.lower().startswith("up"):
				stats = docker_mysql_stats(raw, engine)
				if stats:
					return stats

			return {
				"engine": engine,
				"status": "online" if status_text.lower().startswith("up") else "offline",
				"connection_status": "Docker container " + (raw.get("Names") or raw.get("ID") or "-") + ": " + status_text,
				"database_size_mb": None,
				"slow_queries": None,
				"running_queries": None,
				"threads": None,
				"uptime_seconds": None,
				"last_backup": None,
			}

	return {}


def log_payload():
	items = []
	for log_type, path in {
		"system": "/var/log/syslog",
		"ssh": "/var/log/auth.log",
		"kernel": "/var/log/kern.log",
		"nginx": "/var/log/nginx/error.log",
		"apache": "/var/log/apache2/error.log",
		"mysql": "/var/log/mysql/error.log",
		"firewall": "/var/log/ufw.log",
	}.items():
		for line in tail(path, 5):
			items.append({
				"log_type": log_type,
				"level": level_from_message(line),
				"source": path,
				"message": line.strip()[-2000:],
				"logged_at": now_string(),
			})

	for line in journal_lines(20):
		items.append({
			"log_type": journal_type(line),
			"level": level_from_message(line),
			"source": "journalctl",
			"message": line.strip()[-2000:],
			"logged_at": now_string(),
		})

	for raw in docker_rows()[:8]:
		name = raw.get("Names") or raw.get("ID") or "-"
		for line in docker_log_lines(name, 3):
			items.append({
				"log_type": "docker",
				"level": level_from_message(line),
				"source": "docker:" + name,
				"message": line.strip()[-2000:],
				"logged_at": now_string(),
			})

	items = unique_logs(items)
	if not items:
		items.append({
			"log_type": "system",
			"level": "warning",
			"source": "collector",
			"message": "Tidak ada log yang bisa dibaca oleh user SSH. Cek akses journalctl, docker, atau file /var/log.",
			"logged_at": now_string(),
		})
	return items[-80:]


def tail(path, lines=10):
	if not path or not os.path.exists(path):
		return []
	try:
		with open(path, "rb") as handle:
			handle.seek(0, os.SEEK_END)
			end = handle.tell()
			block = 2048
			data = b""
			while end > 0 and data.count(b"\n") <= lines:
				read_size = min(block, end)
				end -= read_size
				handle.seek(end)
				data = handle.read(read_size) + data
			return [item.decode("utf-8", "ignore") for item in data.splitlines()[-lines:] if item]
	except Exception:
		return []


def journal_lines(lines=20):
	args = ["journalctl", "-n", str(lines), "--no-pager", "-o", "short-iso"]
	output = command(["sudo", "-n"] + args, timeout=5)
	if not output:
		output = command(args, timeout=5)
	return [line for line in output.splitlines() if line.strip()][-lines:]


def journal_type(line):
	lower = line.lower()
	for key in ("nginx", "apache", "mysql", "mariadb", "docker", "ssh", "sshd", "cron", "kernel", "ufw"):
		if key in lower:
			return "firewall" if key == "ufw" else ("ssh" if key == "sshd" else key)
	return "journalctl"


def docker_log_lines(container, lines=3):
	if not container or not docker_available():
		return []
	output = command_combined(["docker", "logs", "--tail", str(lines), container], timeout=5)
	return [line for line in output.splitlines() if line.strip()][-lines:]


def level_from_message(message):
	lower = (message or "").lower()
	if "critical" in lower or "fatal" in lower or "panic" in lower:
		return "critical"
	if "error" in lower or "failed" in lower or "denied" in lower:
		return "error"
	if "warning" in lower or "warn" in lower:
		return "warning"
	if "debug" in lower:
		return "debug"
	return "info"


def unique_logs(items):
	seen = set()
	result = []
	for item in items:
		key = (item.get("log_type"), item.get("source"), item.get("message"))
		if key in seen or not item.get("message"):
			continue
		seen.add(key)
		result.append(item)
	return result


hostname = socket.gethostname()
load = os.getloadavg() if hasattr(os, "getloadavg") else (0, 0, 0)
region = azure_metadata("/compute/location")
public_ip = get_url("https://api.ipify.org", timeout=2)
private_ip_value = private_ip()
current_time = now_string()
system = {
	"hostname": hostname,
	"operating_system": platform.platform(),
	"kernel": platform.release(),
	"architecture": platform.machine(),
	"boot_time": "",
	"current_user": os.environ.get("USER") or os.environ.get("USERNAME") or command(["whoami"]),
	"running_process": len(os.listdir("/proc")) if os.path.exists("/proc") else 0,
	"zombie_process": 0,
	"total_thread": 0,
	"uptime_seconds": uptime_seconds(),
}
memory = memory_payload()
storage = storage_payload()
network = network_payload()
services = service_payload()
docker = docker_payload()
database = database_payload()
websites = website_payload()
logs = log_payload()
cpu = {
	"usage_percent": cpu_usage(),
	"cores": os.cpu_count(),
	"model": cpu_model(),
	"frequency_mhz": 0,
	"load_1": round(load[0], 2),
	"load_5": round(load[1], 2),
	"load_15": round(load[2], 2),
	"top_processes": top_processes("cpu"),
}

payload = {
	"agent_id": "ssh-" + hostname,
	"metric_time": current_time,
	"response_time_ms": 0,
	"latency_ms": 0,
	"server": {
		"agent_id": "ssh-" + hostname,
		"server_name": hostname,
		"hostname": hostname,
		"public_ip": public_ip,
		"private_ip": private_ip_value,
		"provider": "Azure" if region else "SSH Pull",
		"operating_system": system["operating_system"],
		"kernel": system["kernel"],
		"architecture": system["architecture"],
		"uptime_seconds": system["uptime_seconds"],
		"current_time": current_time,
		"timezone": time.tzname[0] if time.tzname else "UTC",
		"azure_region": region,
	},
	"system": system,
	"cpu": cpu,
	"memory": memory,
	"storage": storage,
	"network": network,
	"processes": {
		"top_cpu": cpu["top_processes"],
		"top_memory": memory["top_processes"],
	},
	"services": services,
	"docker": docker,
	"database": database,
	"websites": websites,
	"logs": logs,
}

print(json.dumps(payload))
PY
SH;
	}
}
