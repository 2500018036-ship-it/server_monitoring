<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$servers = isset($monitoring['servers']) ? $monitoring['servers'] : array();
$server = isset($monitoring['server']) ? $monitoring['server'] : NULL;
$metric = isset($monitoring['metric']) ? $monitoring['metric'] : NULL;
$payload = isset($monitoring['payload']) ? $monitoring['payload'] : array();
$alerts = isset($monitoring['alerts']) ? $monitoring['alerts'] : array();
$online_count = 0;

foreach ($servers as $item)
{
	if (isset($item->health_status) && in_array($item->health_status, array('online', 'warning'), TRUE))
	{
		$online_count++;
	}
}

$health_status = $server && isset($server->health_status) ? $server->health_status : 'offline';
$health_badge = $server && isset($server->health_badge) ? $server->health_badge : 'danger';
$health_label = $server && isset($server->health_label) ? $server->health_label : ucfirst($health_status);
$status_box_class = $health_status === 'online' ? 'bg-success' : ($health_status === 'warning' ? 'bg-warning' : 'bg-danger');
$cpu = isset($payload['cpu']) ? $payload['cpu'] : array();
$memory = isset($payload['memory']) ? $payload['memory'] : array();
$storage = isset($payload['storage']) ? $payload['storage'] : array();
$network = isset($payload['network']) ? $payload['network'] : array();
$system = isset($payload['system']) ? $payload['system'] : array();
?>
<div class="content-wrapper" id="realtimeDashboard" data-server-id="<?php echo $server ? (int) $server->id : 0; ?>">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6">
					<h1><?php echo e($page_title); ?></h1>
				</div>
				<div class="col-sm-6">
					<select id="serverSelector" class="form-control realtime-server-selector">
						<?php if (empty($servers)): ?>
							<option value="">Belum ada agent terhubung</option>
						<?php endif; ?>
						<?php foreach ($servers as $item): ?>
							<option value="<?php echo (int) $item->id; ?>" <?php echo $server && (int) $server->id === (int) $item->id ? 'selected' : ''; ?>>
								<?php echo e($item->server_name); ?> - <?php echo e(isset($item->health_label) ? $item->health_label : ucfirst(isset($item->monitoring_status) ? $item->monitoring_status : 'offline')); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div id="sshConnectionIndicator" class="text-right mt-2" data-state="<?php echo $health_status === 'offline' ? 'lost' : 'connected'; ?>">
						<span class="badge badge-<?php echo $health_status === 'offline' ? 'danger' : 'success'; ?>">
							<i class="fas fa-plug mr-1"></i><?php echo $health_status === 'offline' ? 'Lost Connection' : 'Connected'; ?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div id="realtimeAlerts" class="realtime-alerts">
				<?php foreach ($alerts as $alert): ?>
					<div class="alert alert-<?php echo e($alert['level']); ?> alert-dismissible fade show">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<?php echo e($alert['message']); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="row">
				<div class="col-lg-3 col-6">
					<div class="small-box bg-info">
						<div class="inner">
							<h3 id="totalServers"><?php echo count($servers); ?></h3>
							<p>Total Server</p>
						</div>
						<div class="icon"><i class="fas fa-server"></i></div>
					</div>
				</div>
				<div class="col-lg-3 col-6">
					<div class="small-box bg-success">
						<div class="inner">
							<h3 id="onlineServers"><?php echo (int) $online_count; ?></h3>
							<p>Online</p>
						</div>
						<div class="icon"><i class="fas fa-signal"></i></div>
					</div>
				</div>
				<div class="col-lg-3 col-6">
					<div class="small-box bg-warning">
						<div class="inner">
							<h3 id="activeAlerts"><?php echo count($alerts); ?></h3>
							<p>Realtime Alerts</p>
						</div>
						<div class="icon"><i class="fas fa-bell"></i></div>
					</div>
				</div>
				<div class="col-lg-3 col-6">
					<div class="small-box <?php echo e($status_box_class); ?>" id="serverStatusBox">
						<div class="inner">
							<h3 id="serverStatus"><?php echo e($health_label); ?></h3>
							<p>Status</p>
						</div>
						<div class="icon"><i class="fas fa-heartbeat"></i></div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-md-3 col-sm-6 col-12">
					<div class="info-box">
						<span class="info-box-icon bg-primary"><i class="fas fa-microchip"></i></span>
						<div class="info-box-content">
							<span class="info-box-text">CPU Usage</span>
							<span class="info-box-number" id="cpuUsage"><?php echo $metric && $metric->cpu_usage !== NULL ? e($metric->cpu_usage).'%': '-'; ?></span>
						</div>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 col-12">
					<div class="info-box">
						<span class="info-box-icon bg-teal"><i class="fas fa-memory"></i></span>
						<div class="info-box-content">
							<span class="info-box-text">RAM Usage</span>
							<span class="info-box-number" id="memoryUsage"><?php echo $metric && $metric->memory_usage !== NULL ? e($metric->memory_usage).'%': '-'; ?></span>
						</div>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 col-12">
					<div class="info-box">
						<span class="info-box-icon bg-indigo"><i class="fas fa-hdd"></i></span>
						<div class="info-box-content">
							<span class="info-box-text">Disk Usage</span>
							<span class="info-box-number" id="diskUsage"><?php echo $metric && $metric->disk_usage !== NULL ? e($metric->disk_usage).'%': '-'; ?></span>
						</div>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 col-12">
					<div class="info-box">
						<span class="info-box-icon bg-success"><i class="fas fa-network-wired"></i></span>
						<div class="info-box-content">
							<span class="info-box-text">Active Connection</span>
							<span class="info-box-number" id="activeConnections"><?php echo $metric ? (int) $metric->active_connections : '-'; ?></span>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-4">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Server Status</h3>
						</div>
						<div class="card-body p-0">
							<table class="table table-sm mb-0 realtime-kv">
								<tbody>
									<tr><th>Status Server</th><td id="serverHealthStatus"><span class="badge badge-<?php echo e($health_badge); ?>"><?php echo e($health_label); ?></span></td></tr>
									<tr><th>Server Name</th><td id="serverName"><?php echo $server ? e($server->server_name) : '-'; ?></td></tr>
									<tr><th>Hostname</th><td id="hostname"><?php echo $server ? e($server->hostname) : '-'; ?></td></tr>
									<tr><th>Operating System</th><td id="operatingSystem"><?php echo $server ? e($server->operating_system) : '-'; ?></td></tr>
									<tr><th>Kernel</th><td id="kernel"><?php echo $server ? e($server->kernel) : '-'; ?></td></tr>
									<tr><th>Architecture</th><td id="architecture"><?php echo $server ? e($server->architecture) : '-'; ?></td></tr>
									<tr><th>Uptime</th><td id="uptime"><?php echo isset($server->uptime_seconds) ? e($server->uptime_seconds).'s' : '-'; ?></td></tr>
									<tr><th>Current Time</th><td id="currentTime"><?php echo $server ? e($server->current_time) : '-'; ?></td></tr>
									<tr><th>Timezone</th><td id="timezone"><?php echo $server ? e($server->timezone) : '-'; ?></td></tr>
									<tr><th>Public IP</th><td id="publicIp"><?php echo $server ? e($server->public_ip) : '-'; ?></td></tr>
									<tr><th>Private IP</th><td id="privateIp"><?php echo $server ? e($server->private_ip) : '-'; ?></td></tr>
									<tr><th>Azure Region</th><td id="azureRegion"><?php echo $server ? e($server->azure_region) : '-'; ?></td></tr>
									<tr><th>Last Heartbeat</th><td id="lastHeartbeat"><?php echo $server ? e($server->last_heartbeat_at) : '-'; ?></td></tr>
									<tr><th>Latency</th><td id="latency"><?php echo $server && $server->last_latency_ms !== NULL ? e($server->last_latency_ms).' ms' : '-'; ?></td></tr>
									<tr><th>Response Time</th><td id="responseTime"><?php echo $server && $server->last_response_time_ms !== NULL ? e($server->last_response_time_ms).' ms' : '-'; ?></td></tr>
									<tr><th>Current Monitoring Status</th><td id="serverMonitoringStatus"><?php echo $server ? e(ucfirst($server->monitoring_status)) : '-'; ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="col-lg-8">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fas fa-chart-line mr-2"></i>CPU / RAM / Disk</h3>
						</div>
						<div class="card-body chart-card">
							<canvas id="resourceRealtimeChart"></canvas>
						</div>
					</div>
					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fas fa-exchange-alt mr-2"></i>Network Speed</h3>
						</div>
						<div class="card-body chart-card chart-card-sm">
							<canvas id="networkRealtimeChart"></canvas>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-3 col-md-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-microchip mr-2"></i>CPU</h3></div>
						<div class="card-body realtime-metrics">
							<div><span>Core</span><strong id="cpuCores"><?php echo isset($cpu['cores']) ? e($cpu['cores']) : '-'; ?></strong></div>
							<div><span>Model</span><strong id="cpuModel"><?php echo isset($cpu['model']) ? e($cpu['model']) : '-'; ?></strong></div>
							<div><span>Frequency</span><strong id="cpuFreq"><?php echo isset($cpu['frequency_mhz']) ? e($cpu['frequency_mhz']).' MHz' : '-'; ?></strong></div>
							<div><span>Load 1/5/15</span><strong id="loadAverage"><?php echo isset($cpu['load_1']) ? e($cpu['load_1'].' / '.$cpu['load_5'].' / '.$cpu['load_15']) : '-'; ?></strong></div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-memory mr-2"></i>Memory</h3></div>
						<div class="card-body realtime-metrics">
							<div><span>Total RAM</span><strong id="ramTotal"><?php echo isset($memory['total_mb']) ? e($memory['total_mb']).' MB' : '-'; ?></strong></div>
							<div><span>Used RAM</span><strong id="ramUsed"><?php echo isset($memory['used_mb']) ? e($memory['used_mb']).' MB' : '-'; ?></strong></div>
							<div><span>Free RAM</span><strong id="ramFree"><?php echo isset($memory['free_mb']) ? e($memory['free_mb']).' MB' : '-'; ?></strong></div>
							<div><span>Swap Free</span><strong id="swapFree"><?php echo isset($memory['swap_free_mb']) ? e($memory['swap_free_mb']).' MB' : '-'; ?></strong></div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-hdd mr-2"></i>Storage</h3></div>
						<div class="card-body realtime-metrics">
							<div><span>Total</span><strong id="diskTotal"><?php echo isset($storage['disk_total_gb']) ? e($storage['disk_total_gb']).' GB' : '-'; ?></strong></div>
							<div><span>Used</span><strong id="diskUsed"><?php echo isset($storage['disk_used_gb']) ? e($storage['disk_used_gb']).' GB' : '-'; ?></strong></div>
							<div><span>Free</span><strong id="diskFree"><?php echo isset($storage['disk_free_gb']) ? e($storage['disk_free_gb']).' GB' : '-'; ?></strong></div>
							<div><span>Mount</span><strong id="mountPoint"><?php echo isset($storage['mount_point']) ? e($storage['mount_point']) : '-'; ?></strong></div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-network-wired mr-2"></i>Network</h3></div>
						<div class="card-body realtime-metrics">
							<div><span>Upload</span><strong id="uploadSpeed"><?php echo isset($network['upload_speed']) ? e($network['upload_speed']).' B/s' : '-'; ?></strong></div>
							<div><span>Download</span><strong id="downloadSpeed"><?php echo isset($network['download_speed']) ? e($network['download_speed']).' B/s' : '-'; ?></strong></div>
							<div><span>Interface</span><strong id="networkInterface"><?php echo isset($network['interface_name']) ? e($network['interface_name']) : '-'; ?></strong></div>
							<div><span>Packet Loss</span><strong id="packetLoss"><?php echo isset($network['packet_loss']) ? e($network['packet_loss']).'%' : '-'; ?></strong></div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-list-ol mr-2"></i>Top CPU Process</h3></div>
						<div class="card-body table-responsive">
							<table class="table table-sm table-striped realtime-table">
								<thead><tr><th>PID</th><th>User</th><th>Command</th><th>CPU</th><th>RAM</th><th>Time</th></tr></thead>
								<tbody id="topCpuProcessRows"></tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-list-ol mr-2"></i>Top Memory Process</h3></div>
						<div class="card-body table-responsive">
							<table class="table table-sm table-striped realtime-table">
								<thead><tr><th>PID</th><th>User</th><th>Command</th><th>CPU</th><th>RAM</th><th>Time</th></tr></thead>
								<tbody id="topMemoryProcessRows"></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-cogs mr-2"></i>Services</h3></div>
						<div class="card-body table-responsive">
							<table class="table table-sm table-bordered realtime-table">
								<thead><tr><th>Service</th><th>Status</th><th>Last Check</th><th>Log</th></tr></thead>
								<tbody id="serviceRows"></tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fab fa-docker mr-2"></i>Docker</h3></div>
						<div class="card-body table-responsive">
							<table class="table table-sm table-bordered realtime-table">
								<thead><tr><th>Container</th><th>Image</th><th>Status</th><th>CPU</th><th>RAM</th></tr></thead>
								<tbody id="dockerRows"></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-globe mr-2"></i>Website</h3></div>
						<div class="card-body table-responsive">
							<table class="table table-sm table-bordered realtime-table">
								<thead><tr><th>Domain</th><th>Status</th><th>HTTP</th><th>Response</th><th>Last Check</th></tr></thead>
								<tbody id="websiteRows"></tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-database mr-2"></i>Database</h3></div>
						<div class="card-body table-responsive">
							<table class="table table-sm table-bordered realtime-table">
								<thead><tr><th>Engine</th><th>Status</th><th>Size</th><th>Threads</th><th>Uptime</th></tr></thead>
								<tbody id="databaseRows"></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-stream mr-2"></i>Realtime Logs</h3>
				</div>
				<div class="card-body table-responsive realtime-log-panel">
					<table class="table table-sm table-striped realtime-table">
						<thead><tr><th>Time</th><th>Type</th><th>Level</th><th>Source</th><th>Message</th></tr></thead>
						<tbody id="logRows"></tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
