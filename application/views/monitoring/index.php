<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$servers = isset($monitoring['servers']) ? $monitoring['servers'] : array();
$server = isset($monitoring['server']) ? $monitoring['server'] : NULL;
$payload = isset($monitoring['payload']) ? $monitoring['payload'] : array();
$system = isset($payload['system']) ? $payload['system'] : array();
$processes = isset($payload['processes']) ? $payload['processes'] : array();
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6">
					<select class="form-control realtime-server-selector" onchange="location.href='<?php echo site_url('monitoring?server_id='); ?>' + this.value">
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
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-desktop mr-2"></i>System</h3></div>
						<div class="card-body p-0">
							<table class="table table-sm realtime-kv mb-0">
								<tbody>
									<tr><th>Hostname</th><td><?php echo e(isset($system['hostname']) ? $system['hostname'] : ($server ? $server->hostname : '-')); ?></td></tr>
									<tr><th>Operating System</th><td><?php echo e(isset($system['operating_system']) ? $system['operating_system'] : ($server ? $server->operating_system : '-')); ?></td></tr>
									<tr><th>Kernel</th><td><?php echo e(isset($system['kernel']) ? $system['kernel'] : ($server ? $server->kernel : '-')); ?></td></tr>
									<tr><th>Architecture</th><td><?php echo e(isset($system['architecture']) ? $system['architecture'] : ($server ? $server->architecture : '-')); ?></td></tr>
									<tr><th>Boot Time</th><td><?php echo e(isset($system['boot_time']) ? $system['boot_time'] : '-'); ?></td></tr>
									<tr><th>Current User</th><td><?php echo e(isset($system['current_user']) ? $system['current_user'] : '-'); ?></td></tr>
									<tr><th>Running Process</th><td><?php echo e(isset($system['running_process']) ? $system['running_process'] : '-'); ?></td></tr>
									<tr><th>Zombie Process</th><td><?php echo e(isset($system['zombie_process']) ? $system['zombie_process'] : '-'); ?></td></tr>
									<tr><th>Total Thread</th><td><?php echo e(isset($system['total_thread']) ? $system['total_thread'] : '-'); ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-cogs mr-2"></i>Services</h3></div>
						<div class="card-body table-responsive">
							<table class="table table-sm table-bordered datatable datatable-export">
								<thead><tr><th>Service</th><th>Status</th><th>Log</th></tr></thead>
								<tbody>
									<?php foreach ((isset($payload['services']) ? $payload['services'] : array()) as $service): ?>
										<tr>
											<td><?php echo e(isset($service['name']) ? $service['name'] : '-'); ?></td>
											<td><span class="badge badge-<?php echo isset($service['status']) && $service['status'] === 'running' ? 'success' : 'danger'; ?>"><?php echo e(isset($service['status']) ? $service['status'] : 'unknown'); ?></span></td>
											<td><?php echo e(isset($service['log_excerpt']) ? $service['log_excerpt'] : '-'); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-tasks mr-2"></i>Top 20 Processes</h3></div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover datatable datatable-export">
						<thead><tr><th>Type</th><th>PID</th><th>User</th><th>Command</th><th>CPU</th><th>RAM</th><th>Running Time</th></tr></thead>
						<tbody>
							<?php foreach (array('CPU' => 'top_cpu', 'Memory' => 'top_memory') as $label => $key): ?>
								<?php foreach ((isset($processes[$key]) ? $processes[$key] : array()) as $process): ?>
									<tr>
										<td><?php echo e($label); ?></td>
										<td><?php echo e(isset($process['pid']) ? $process['pid'] : '-'); ?></td>
										<td><?php echo e(isset($process['user']) ? $process['user'] : '-'); ?></td>
										<td><?php echo e(isset($process['command']) ? $process['command'] : '-'); ?></td>
										<td><?php echo e(isset($process['cpu']) ? $process['cpu'].'%' : '-'); ?></td>
										<td><?php echo e(isset($process['ram']) ? $process['ram'].'%' : '-'); ?></td>
										<td><?php echo e(isset($process['running_time']) ? $process['running_time'] : '-'); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
