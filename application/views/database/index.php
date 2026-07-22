<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$servers = $monitoring['servers'];
$server = $monitoring['server'];
$database = isset($monitoring['payload']['database']) ? $monitoring['payload']['database'] : array();
$stats_ok = isset($statistics['ok']) && $statistics['ok'];
$db_size = $stats_ok ? $statistics['total_size_mb'] : (isset($database['database_size_mb']) ? $database['database_size_mb'] : 0);
$last_backup_text = $last_backup ? $last_backup->created_at.' - '.$last_backup->file_name : '-';
$last_restore_text = $last_restore ? $last_restore->created_at.' - '.$last_restore->file_name : '-';
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6">
					<select class="form-control realtime-server-selector" onchange="location.href='<?php echo site_url('database?server_id='); ?>' + this.value">
						<?php foreach ($servers as $item): ?>
							<option value="<?php echo (int) $item->id; ?>" <?php echo $server && (int) $server->id === (int) $item->id ? 'selected' : ''; ?>><?php echo e($item->server_name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-3 col-6">
					<div class="small-box bg-info">
						<div class="inner"><h3><?php echo $stats_ok ? (int) $statistics['total_database'] : '-'; ?></h3><p>Total Database</p></div>
						<div class="icon"><i class="fas fa-database"></i></div>
					</div>
				</div>
				<div class="col-lg-3 col-6">
					<div class="small-box bg-success">
						<div class="inner"><h3><?php echo $stats_ok ? (int) $statistics['total_table'] : '-'; ?></h3><p>Total Table</p></div>
						<div class="icon"><i class="fas fa-table"></i></div>
					</div>
				</div>
				<div class="col-lg-3 col-6">
					<div class="small-box bg-warning">
						<div class="inner"><h3><?php echo $stats_ok ? number_format((float) $statistics['total_record']) : '-'; ?></h3><p>Total Record</p></div>
						<div class="icon"><i class="fas fa-list-ol"></i></div>
					</div>
				</div>
				<div class="col-lg-3 col-6">
					<div class="small-box bg-danger">
						<div class="inner"><h3><?php echo e($db_size); ?> MB</h3><p>Total Size</p></div>
						<div class="icon"><i class="fas fa-hdd"></i></div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Dashboard Database</h3>
					<div class="card-tools">
						<?php $selected_config_query = $selected_config ? '?ssh_config_id='.(int) $selected_config->id : ''; ?>
						<a href="<?php echo site_url('database/explorer'.$selected_config_query); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-search mr-1"></i> Explorer</a>
						<a href="<?php echo site_url('database/history'); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-history mr-1"></i> Backup History</a>
						<a href="<?php echo site_url('database/query'.$selected_config_query); ?>" class="btn btn-sm btn-outline-dark"><i class="fas fa-code mr-1"></i> SQL Query</a>
						<a href="<?php echo site_url('database/backup-config'); ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-cog mr-1"></i> Backup Config</a>
					</div>
				</div>
				<div class="card-body p-0">
					<table class="table table-sm realtime-kv mb-0">
						<tbody>
							<tr><th>Last Backup</th><td><?php echo e($last_backup_text); ?></td></tr>
							<tr><th>Backup Size</th><td><?php echo $last_backup && $last_backup->file_size_bytes ? e(number_format($last_backup->file_size_bytes / 1024 / 1024, 2)).' MB' : '-'; ?></td></tr>
							<tr><th>Database Size</th><td><?php echo e($db_size); ?> MB</td></tr>
							<tr><th>Total Database</th><td><?php echo $stats_ok ? (int) $statistics['total_database'] : '-'; ?></td></tr>
							<tr><th>Total Table</th><td><?php echo $stats_ok ? (int) $statistics['total_table'] : '-'; ?></td></tr>
							<tr><th>Total Record</th><td><?php echo $stats_ok ? number_format((float) $statistics['total_record']) : '-'; ?></td></tr>
							<tr><th>Last Restore</th><td><?php echo e($last_restore_text); ?></td></tr>
							<tr><th>MySQL Status</th><td><?php echo e($stats_ok ? $statistics['status'] : (isset($database['status']) ? $database['status'] : '-')); ?></td></tr>
							<tr><th>Connection Status</th><td><?php echo e($stats_ok ? $statistics['connection_status'] : (isset($database['connection_status']) ? $database['connection_status'] : '-')); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-database mr-2"></i>Database Manager</h3></div>
				<div class="card-body">
					<?php echo form_open('database/action', array('class' => 'confirm-form', 'data-confirm' => 'Jalankan database action?')); ?>
						<div class="row">
							<div class="col-md-3">
								<select name="ssh_config_id" class="form-control" required>
									<?php foreach ($configs as $config): ?>
										<option value="<?php echo (int) $config->id; ?>" <?php echo $selected_config && (int) $selected_config->id === (int) $config->id ? 'selected' : ''; ?>><?php echo e($config->name); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-3">
								<select name="database_action" class="form-control" required>
									<option value="status">Status</option>
									<option value="restart">Restart Database</option>
									<option value="backup">Backup Database</option>
									<option value="export_sql">Export SQL</option>
									<option value="optimize">Optimize Database</option>
									<option value="repair">Repair Database</option>
									<option value="running_query">View Running Query</option>
								</select>
							</div>
							<div class="col-md-4">
								<input type="text" name="database_name" class="form-control" placeholder="Container atau database name">
							</div>
							<div class="col-md-2">
								<button class="btn btn-primary btn-block"><i class="fas fa-play mr-1"></i> Run</button>
							</div>
						</div>
					<?php echo form_close(); ?>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-server mr-2"></i>Database Statistics</h3></div>
						<div class="card-body p-0">
							<table class="table table-sm realtime-kv mb-0">
								<tbody>
									<tr><th>MySQL Version</th><td><?php echo e($stats_ok ? $statistics['mysql_version'] : '-'); ?></td></tr>
									<tr><th>MariaDB Version</th><td><?php echo e($stats_ok ? $statistics['mariadb_version'] : '-'); ?></td></tr>
									<tr><th>Uptime Database</th><td><?php echo e($stats_ok ? $statistics['uptime_seconds'].' s' : (isset($database['uptime_seconds']) ? $database['uptime_seconds'].' s' : '-')); ?></td></tr>
									<tr><th>Active Connection</th><td><?php echo e($stats_ok ? $statistics['active_connection'] : '-'); ?></td></tr>
									<tr><th>Max Connection</th><td><?php echo e($stats_ok ? $statistics['max_connection'] : '-'); ?></td></tr>
									<tr><th>Slow Query Count</th><td><?php echo e($stats_ok ? $statistics['slow_query_count'] : (isset($database['slow_queries']) ? $database['slow_queries'] : '-')); ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-2"></i>Recent Backup</h3></div>
						<div class="card-body table-responsive p-0">
							<table class="table table-sm table-striped mb-0">
								<thead><tr><th>File</th><th>Database</th><th>Size</th><th>Status</th><th>Date</th></tr></thead>
								<tbody>
									<?php foreach ($recent_backups as $backup): ?>
										<tr>
											<td><?php echo e($backup->file_name ?: '-'); ?></td>
											<td><?php echo e($backup->database_name ?: 'all-databases'); ?></td>
											<td><?php echo $backup->file_size_bytes ? e(number_format($backup->file_size_bytes / 1024 / 1024, 2)).' MB' : '-'; ?></td>
											<td><span class="badge badge-<?php echo $backup->status === 'success' ? 'success' : ($backup->status === 'failed' ? 'danger' : 'warning'); ?>"><?php echo e($backup->status); ?></span></td>
											<td><?php echo e($backup->created_at); ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($recent_backups)): ?>
										<tr><td colspan="5" class="text-center text-muted">Belum ada riwayat backup.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-database mr-2"></i>MySQL / MariaDB</h3></div>
				<div class="card-body p-0">
					<table class="table table-sm realtime-kv mb-0">
						<tbody>
							<tr><th>Status</th><td><?php echo e(isset($database['status']) ? $database['status'] : '-'); ?></td></tr>
							<tr><th>Connection</th><td><?php echo e(isset($database['connection_status']) ? $database['connection_status'] : '-'); ?></td></tr>
							<tr><th>Database Size</th><td><?php echo e(isset($database['database_size_mb']) ? $database['database_size_mb'].' MB' : '-'); ?></td></tr>
							<tr><th>Slow Query</th><td><?php echo e(isset($database['slow_queries']) ? $database['slow_queries'] : '-'); ?></td></tr>
							<tr><th>Running Query</th><td><?php echo e(isset($database['running_queries']) ? $database['running_queries'] : '-'); ?></td></tr>
							<tr><th>Thread</th><td><?php echo e(isset($database['threads']) ? $database['threads'] : '-'); ?></td></tr>
							<tr><th>Uptime</th><td><?php echo e(isset($database['uptime_seconds']) ? $database['uptime_seconds'].' s' : '-'); ?></td></tr>
							<tr><th>Last Backup</th><td><?php echo e($last_backup_text); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
