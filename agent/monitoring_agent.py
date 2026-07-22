#!/usr/bin/env python3
import json
import os
import platform
import pwd
import socket
import ssl
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone

try:
	import psutil
except ImportError:
	psutil = None


API_URL = os.environ.get("SM_API_URL", "https://example.com/index.php/api/metrics")
API_KEY = os.environ.get("SM_API_KEY", "change-me")
AGENT_ID = os.environ.get("SM_AGENT_ID", socket.gethostname())
SERVER_NAME = os.environ.get("SM_SERVER_NAME", socket.gethostname())
INTERVAL = int(os.environ.get("SM_INTERVAL", "1"))
VERIFY_TLS = os.environ.get("SM_VERIFY_TLS", "1") == "1"
WEBSITES = [item.strip() for item in os.environ.get("SM_WEBSITES", "").split(",") if item.strip()]
SERVICES = [item.strip() for item in os.environ.get("SM_SERVICES", "nginx,apache2,httpd,php-fpm,mysql,mariadb,docker,ssh,sshd,cron,crond").split(",") if item.strip()]
LOG_FILES = {
	"system": "/var/log/syslog",
	"nginx": "/var/log/nginx/error.log",
	"apache": "/var/log/apache2/error.log",
	"php": "/var/log/php/error.log",
	"mysql": "/var/log/mysql/error.log",
	"ssh": "/var/log/auth.log",
	"kernel": "/var/log/kern.log",
	"cron": "/var/log/cron.log",
	"firewall": "/var/log/ufw.log",
}


class NetworkSpeed:
	def __init__(self):
		self.previous = None
		self.previous_time = None

	def snapshot(self):
		if not psutil:
			return {}

		now = time.time()
		counters = psutil.net_io_counters(pernic=True)
		current = {}
		result = {}

		for interface, data in counters.items():
			if interface == "lo":
				continue

			current[interface] = data
			previous_data = self.previous.get(interface) if self.previous else None
			elapsed = max(now - self.previous_time, 1) if self.previous_time else 1
			upload_speed = int((data.bytes_sent - previous_data.bytes_sent) / elapsed) if previous_data else 0
			download_speed = int((data.bytes_recv - previous_data.bytes_recv) / elapsed) if previous_data else 0

			result = {
				"interface_name": interface,
				"upload_speed": max(upload_speed, 0),
				"download_speed": max(download_speed, 0),
				"total_upload": data.bytes_sent,
				"total_download": data.bytes_recv,
				"packet_sent": data.packets_sent,
				"packet_received": data.packets_recv,
				"packet_loss": 0,
				"active_connections": active_connections(),
			}
			break

		self.previous = current
		self.previous_time = now
		return result


class DiskSpeed:
	def __init__(self):
		self.previous = None
		self.previous_time = None

	def snapshot(self):
		if not psutil:
			return {"disk_read_speed": 0, "disk_write_speed": 0, "iops": 0}

		now = time.time()
		data = psutil.disk_io_counters()
		if not data:
			return {"disk_read_speed": 0, "disk_write_speed": 0, "iops": 0}

		if not self.previous:
			self.previous = data
			self.previous_time = now
			return {"disk_read_speed": 0, "disk_write_speed": 0, "iops": 0}

		elapsed = max(now - self.previous_time, 1)
		read_speed = int((data.read_bytes - self.previous.read_bytes) / elapsed)
		write_speed = int((data.write_bytes - self.previous.write_bytes) / elapsed)
		iops = ((data.read_count - self.previous.read_count) + (data.write_count - self.previous.write_count)) / elapsed
		self.previous = data
		self.previous_time = now

		return {
			"disk_read_speed": max(read_speed, 0),
			"disk_write_speed": max(write_speed, 0),
			"iops": round(max(iops, 0), 2),
		}


network_speed = NetworkSpeed()
disk_speed = DiskSpeed()


def command(args, timeout=4):
	try:
		return subprocess.check_output(args, stderr=subprocess.DEVNULL, timeout=timeout, text=True).strip()
	except Exception:
		return ""


def command_combined(args, timeout=4):
	try:
		return subprocess.check_output(args, stderr=subprocess.STDOUT, timeout=timeout, text=True).strip()
	except Exception:
		return ""


def get_url(url, timeout=2, headers=None):
	request = urllib.request.Request(url, headers=headers or {})
	try:
		with urllib.request.urlopen(request, timeout=timeout) as response:
			return response.read().decode("utf-8", "ignore")
	except Exception:
		return ""


def azure_metadata(path):
	base = "http://169.254.169.254/metadata/instance"
	query = "?api-version=2021-02-01&format=text"
	return get_url(base + path + query, timeout=1, headers={"Metadata": "true"})


def public_ip():
	return get_url("https://api.ipify.org", timeout=2)


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
	if psutil:
		return int(time.time() - psutil.boot_time())

	try:
		with open("/proc/uptime", "r", encoding="utf-8") as handle:
			return int(float(handle.read().split()[0]))
	except Exception:
		return 0


def boot_time():
	if psutil:
		return datetime.fromtimestamp(psutil.boot_time()).strftime("%Y-%m-%d %H:%M:%S")

	return ""


def cpu_payload():
	load = os.getloadavg() if hasattr(os, "getloadavg") else (0, 0, 0)
	model = ""
	frequency = 0

	if psutil:
		freq = psutil.cpu_freq()
		frequency = round(freq.current, 2) if freq else 0
		usage = psutil.cpu_percent(interval=0.2)
	else:
		usage = 0

	try:
		with open("/proc/cpuinfo", "r", encoding="utf-8") as handle:
			for line in handle:
				if line.lower().startswith("model name"):
					model = line.split(":", 1)[1].strip()
					break
	except Exception:
		model = platform.processor()

	return {
		"usage_percent": usage,
		"cores": psutil.cpu_count() if psutil else os.cpu_count(),
		"model": model,
		"frequency_mhz": frequency,
		"load_1": round(load[0], 2),
		"load_5": round(load[1], 2),
		"load_15": round(load[2], 2),
		"top_processes": top_processes("cpu"),
	}


def memory_payload():
	if not psutil:
		return {"top_processes": top_processes("memory")}

	virtual = psutil.virtual_memory()
	swap = psutil.swap_memory()

	return {
		"total_mb": bytes_to_mb(virtual.total),
		"used_mb": bytes_to_mb(virtual.used),
		"free_mb": bytes_to_mb(virtual.available),
		"cache_mb": bytes_to_mb(getattr(virtual, "cached", 0)),
		"buffer_mb": bytes_to_mb(getattr(virtual, "buffers", 0)),
		"swap_used_mb": bytes_to_mb(swap.used),
		"swap_free_mb": bytes_to_mb(swap.free),
		"usage_percent": virtual.percent,
		"top_processes": top_processes("memory"),
	}


def storage_payload():
	speed = disk_speed.snapshot()
	if not psutil:
		return speed

	disks = []
	for partition in psutil.disk_partitions(all=False):
		try:
			usage = psutil.disk_usage(partition.mountpoint)
		except PermissionError:
			continue

		disks.append({
			"mount_point": partition.mountpoint,
			"disk_total_gb": bytes_to_gb(usage.total),
			"disk_used_gb": bytes_to_gb(usage.used),
			"disk_free_gb": bytes_to_gb(usage.free),
			"disk_percentage": usage.percent,
			"disk_read_speed": speed["disk_read_speed"],
			"disk_write_speed": speed["disk_write_speed"],
			"iops": speed["iops"],
		})

	root = disks[0] if disks else speed
	root["disks"] = disks
	return root


def network_payload():
	return network_speed.snapshot()


def system_payload():
	return {
		"hostname": socket.gethostname(),
		"operating_system": platform.platform(),
		"kernel": platform.release(),
		"architecture": platform.machine(),
		"boot_time": boot_time(),
		"current_user": command(["whoami"]),
		"running_process": len(psutil.pids()) if psutil else 0,
		"zombie_process": zombie_processes(),
		"total_thread": total_threads(),
		"uptime_seconds": uptime_seconds(),
	}


def top_processes(kind):
	if not psutil:
		return []

	items = []
	for proc in psutil.process_iter(["pid", "username", "name", "cmdline", "cpu_percent", "memory_percent", "create_time"]):
		try:
			info = proc.info
			command_text = " ".join(info.get("cmdline") or []) or info.get("name") or ""
			running_time = int(time.time() - (info.get("create_time") or time.time()))
			items.append({
				"pid": info.get("pid"),
				"user": info.get("username") or "",
				"command": command_text[:500],
				"cpu": round(info.get("cpu_percent") or 0, 2),
				"ram": round(info.get("memory_percent") or 0, 2),
				"running_time": str(running_time) + "s",
			})
		except (psutil.NoSuchProcess, psutil.AccessDenied):
			continue

	key = "ram" if kind == "memory" else "cpu"
	return sorted(items, key=lambda item: item[key], reverse=True)[:20]


def zombie_processes():
	if not psutil:
		return 0

	count = 0
	for proc in psutil.process_iter(["status"]):
		try:
			if proc.info["status"] == psutil.STATUS_ZOMBIE:
				count += 1
		except (psutil.NoSuchProcess, psutil.AccessDenied):
			continue
	return count


def total_threads():
	if not psutil:
		return 0

	total = 0
	for proc in psutil.process_iter(["num_threads"]):
		try:
			total += proc.info.get("num_threads") or 0
		except (psutil.NoSuchProcess, psutil.AccessDenied):
			continue
	return total


def active_connections():
	if not psutil:
		return 0

	try:
		return len(psutil.net_connections(kind="inet"))
	except Exception:
		return 0


def service_payload():
	items = []
	seen = set()

	for service in SERVICES:
		if service in seen:
			continue
		seen.add(service)
		status = command(["systemctl", "is-active", service])
		items.append({
			"name": service,
			"status": "running" if status == "active" else "stopped",
			"log_excerpt": journal_excerpt(service),
		})

	return items


def journal_excerpt(service):
	text = command(["journalctl", "-u", service, "-n", "3", "--no-pager"], timeout=3)
	return text[-1000:] if text else ""


def docker_payload():
	if not command(["which", "docker"]):
		return {"available": False, "containers": []}

	output = command(["docker", "ps", "-a", "--format", "{{json .}}"], timeout=5)
	containers = []

	for line in output.splitlines():
		try:
			raw = json.loads(line)
		except json.JSONDecodeError:
			continue

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


def database_payload():
	if not command(["which", "mysqladmin"]):
		return {"engine": "mysql", "status": "unknown", "connection_status": "mysqladmin not found"}

	ping = command(["mysqladmin", "ping"], timeout=3)
	status = command(["mysqladmin", "status"], timeout=3)
	return {
		"engine": "mysql",
		"status": "online" if "alive" in ping.lower() else "offline",
		"connection_status": ping,
		"database_size_mb": database_size_mb(),
		"slow_queries": parse_status_number(status, "Slow queries:"),
		"running_queries": parse_status_number(status, "Queries per second avg:"),
		"threads": parse_status_number(status, "Threads:"),
		"uptime_seconds": parse_status_number(status, "Uptime:"),
		"last_backup": None,
	}


def database_size_mb():
	query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables;"
	output = command(["mysql", "-N", "-e", query], timeout=5)
	try:
		return float(output)
	except ValueError:
		return None


def parse_status_number(text, label):
	if label not in text:
		return None
	try:
		return float(text.split(label, 1)[1].strip().split()[0])
	except Exception:
		return None


def website_payload():
	items = []
	for url in WEBSITES:
		start = time.time()
		status = "offline"
		http_status = None
		try:
			request = urllib.request.Request(url, headers={"User-Agent": "ServerMonitoringAgent/1.0"})
			with urllib.request.urlopen(request, timeout=5) as response:
				http_status = response.status
				status = "online" if response.status < 500 else "offline"
		except Exception:
			status = "offline"

		parsed = urllib.parse.urlparse(url if "://" in url else "https://" + url)
		items.append({
			"domain": parsed.netloc or parsed.path,
			"status": status,
			"http_status": http_status,
			"response_time_ms": int((time.time() - start) * 1000),
			"ssl_expired_at": ssl_expiry(parsed.netloc or parsed.path),
			"ping_ms": None,
			"dns_resolve_ms": dns_time(parsed.netloc or parsed.path),
			"last_check": now_string(),
		})
	return items


def ssl_expiry(hostname):
	if not hostname:
		return None

	try:
		context = ssl.create_default_context()
		with socket.create_connection((hostname, 443), timeout=4) as sock:
			with context.wrap_socket(sock, server_hostname=hostname) as ssock:
				cert = ssock.getpeercert()
		expires = datetime.strptime(cert["notAfter"], "%b %d %H:%M:%S %Y %Z")
		return expires.strftime("%Y-%m-%d %H:%M:%S")
	except Exception:
		return None


def dns_time(hostname):
	start = time.time()
	try:
		socket.gethostbyname(hostname)
		return round((time.time() - start) * 1000, 2)
	except Exception:
		return None


def logs_payload():
	items = []
	for log_type, path in LOG_FILES.items():
		for line in tail(path, 5):
			items.append({
				"log_type": log_type,
				"level": level_from_message(line),
				"source": path,
				"message": line[-2000:],
				"logged_at": now_string(),
			})

	for line in journal_lines(20):
		items.append({
			"log_type": journal_type(line),
			"level": level_from_message(line),
			"source": "journalctl",
			"message": line[-2000:],
			"logged_at": now_string(),
		})

	for container in docker_container_names()[:8]:
		for line in docker_log_lines(container, 3):
			items.append({
				"log_type": "docker",
				"level": level_from_message(line),
				"source": "docker:" + container,
				"message": line[-2000:],
				"logged_at": now_string(),
			})

	items = unique_logs(items)
	if not items:
		items.append({
			"log_type": "system",
			"level": "warning",
			"source": "collector",
			"message": "Tidak ada log yang bisa dibaca oleh user agent. Cek akses journalctl, docker, atau file /var/log.",
			"logged_at": now_string(),
		})
	return items[-50:]


def tail(path, lines=10):
	if not path or not os.path.exists(path):
		return []

	try:
		with open(path, "rb") as handle:
			handle.seek(0, os.SEEK_END)
			end = handle.tell()
			block = 1024
			data = b""
			while end > 0 and data.count(b"\n") <= lines:
				read_size = min(block, end)
				end -= read_size
				handle.seek(end)
				data = handle.read(read_size) + data
			return [item.decode("utf-8", "ignore") for item in data.splitlines()[-lines:]]
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


def docker_container_names():
	if not command(["which", "docker"]):
		return []
	output = command(["docker", "ps", "-a", "--format", "{{.Names}}"], timeout=5)
	return [line.strip() for line in output.splitlines() if line.strip()]


def docker_log_lines(container, lines=3):
	if not container:
		return []
	output = command_combined(["docker", "logs", "--tail", str(lines), container], timeout=5)
	return [line for line in output.splitlines() if line.strip()][-lines:]


def level_from_message(message):
	lower = message.lower()
	if "critical" in lower or "fatal" in lower:
		return "critical"
	if "error" in lower or "failed" in lower:
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


def bytes_to_mb(value):
	return int(value / 1024 / 1024)


def bytes_to_gb(value):
	return round(value / 1024 / 1024 / 1024, 2)


def now_string():
	return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def payload():
	region = azure_metadata("/compute/location") or os.environ.get("SM_AZURE_REGION", "")
	server = {
		"agent_id": AGENT_ID,
		"server_name": SERVER_NAME,
		"hostname": socket.gethostname(),
		"public_ip": public_ip(),
		"private_ip": private_ip(),
		"provider": "Azure" if region else os.environ.get("SM_PROVIDER", "Linux VPS"),
		"operating_system": platform.platform(),
		"kernel": platform.release(),
		"architecture": platform.machine(),
		"uptime_seconds": uptime_seconds(),
		"current_time": now_string(),
		"timezone": time.tzname[0] if time.tzname else "UTC",
		"azure_region": region,
	}

	return {
		"agent_id": AGENT_ID,
		"metric_time": now_string(),
		"response_time_ms": 0,
		"latency_ms": 0,
		"server": server,
		"system": system_payload(),
		"cpu": cpu_payload(),
		"memory": memory_payload(),
		"storage": storage_payload(),
		"network": network_payload(),
		"processes": {
			"top_cpu": top_processes("cpu"),
			"top_memory": top_processes("memory"),
		},
		"services": service_payload(),
		"docker": docker_payload(),
		"database": database_payload(),
		"websites": website_payload(),
		"logs": logs_payload(),
	}


def post_metrics(data):
	raw = json.dumps(data).encode("utf-8")
	request = urllib.request.Request(
		API_URL,
		data=raw,
		headers={
			"Content-Type": "application/json",
			"X-API-Key": API_KEY,
			"User-Agent": "ServerMonitoringAgent/1.0",
		},
		method="POST",
	)
	context = None if VERIFY_TLS else ssl._create_unverified_context()
	start = time.time()
	with urllib.request.urlopen(request, timeout=10, context=context) as response:
		response.read()
		return int((time.time() - start) * 1000), response.status


def main():
	if not API_KEY or API_KEY == "change-me":
		raise SystemExit("SM_API_KEY is required")

	while True:
		try:
			data = payload()
			response_time, status = post_metrics(data)
			print(now_string(), "posted metrics", status, response_time, "ms", flush=True)
		except urllib.error.HTTPError as error:
			print(now_string(), "http error", error.code, error.read().decode("utf-8", "ignore"), flush=True)
		except Exception as error:
			print(now_string(), "agent error", str(error), flush=True)
		time.sleep(max(INTERVAL, 1))


if __name__ == "__main__":
	main()
