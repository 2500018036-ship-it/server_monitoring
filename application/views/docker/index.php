<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$servers = $monitoring['servers'];
$server = $monitoring['server'];
$containers = isset($monitoring['payload']['docker']['containers']) ? $monitoring['payload']['docker']['containers'] : array();
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6">
					<select class="form-control realtime-server-selector" onchange="location.href='<?php echo site_url('docker?server_id='); ?>' + this.value">
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
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fab fa-docker mr-2"></i>Docker Manager</h3></div>
				<div class="card-body">
					<?php echo form_open('docker/action', array('class' => 'confirm-form', 'data-confirm' => 'Jalankan Docker action?')); ?>
						<div class="row">
							<div class="col-md-3">
								<select name="ssh_config_id" class="form-control" required>
									<?php foreach ($configs as $config): ?>
										<option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-3">
								<select name="docker_action" class="form-control" required>
									<option value="start">Start Container</option>
									<option value="stop">Stop Container</option>
									<option value="restart">Restart Container</option>
									<option value="logs">View Log</option>
									<option value="pull_image">Pull Image</option>
									<option value="remove_image">Remove Image</option>
								</select>
							</div>
							<div class="col-md-4">
								<input type="text" name="target" class="form-control" placeholder="Container name / image" required>
							</div>
							<div class="col-md-2">
								<button class="btn btn-primary btn-block"><i class="fas fa-play mr-1"></i> Run</button>
							</div>
						</div>
					<?php echo form_close(); ?>
				</div>
			</div>
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fab fa-docker mr-2"></i>Docker Containers</h3></div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover datatable datatable-export">
						<thead><tr><th>Container</th><th>Image</th><th>Status</th><th>CPU</th><th>RAM</th><th>Restart</th><th>Port</th><th>Network</th><th>Volume</th><th>Action</th></tr></thead>
						<tbody>
							<?php foreach ($containers as $container): ?>
								<tr>
									<td><?php echo e(isset($container['container_name']) ? $container['container_name'] : (isset($container['container_id']) ? $container['container_id'] : '-')); ?></td>
									<td><?php echo e(isset($container['image']) ? $container['image'] : '-'); ?></td>
									<td><?php echo e(isset($container['status']) ? $container['status'] : '-'); ?></td>
									<td><?php echo e(isset($container['cpu']) ? $container['cpu'].'%' : '-'); ?></td>
									<td><?php echo e(isset($container['ram']) ? $container['ram'] : '-'); ?></td>
									<td><?php echo e(isset($container['restart_count']) ? $container['restart_count'] : '-'); ?></td>
									<td><?php echo e(isset($container['ports']) ? $container['ports'] : '-'); ?></td>
									<td><?php echo e(isset($container['network']) ? $container['network'] : '-'); ?></td>
									<td><?php echo e(isset($container['volume']) ? $container['volume'] : '-'); ?></td>
									<td>
										<button class="btn btn-sm btn-outline-secondary" disabled><i class="fas fa-play"></i></button>
										<button class="btn btn-sm btn-outline-secondary" disabled><i class="fas fa-stop"></i></button>
										<button class="btn btn-sm btn-outline-secondary" disabled><i class="fas fa-sync"></i></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
