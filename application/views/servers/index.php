<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-server mr-2"></i>Registered Servers</h3>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-bordered table-hover datatable datatable-export">
							<thead>
								<tr>
									<th>Server</th>
									<th>Hostname</th>
									<th>OS</th>
									<th>Provider</th>
									<th>Public IP</th>
									<th>Private IP</th>
									<th>Region</th>
									<th>Status</th>
									<th>Last Heartbeat</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($servers as $server): ?>
									<tr>
										<td>
											<a href="<?php echo site_url('dashboard?server_id='.(int) $server->id); ?>"><?php echo e($server->server_name); ?></a>
											<div class="small text-muted"><?php echo e($server->agent_id); ?></div>
										</td>
										<td><?php echo e($server->hostname); ?></td>
										<td><?php echo e($server->operating_system); ?></td>
										<td><?php echo e($server->provider); ?></td>
										<td><?php echo e($server->public_ip); ?></td>
										<td><?php echo e($server->private_ip); ?></td>
										<td><?php echo e($server->azure_region); ?></td>
										<td>
											<span class="badge badge-<?php echo $server->monitoring_status === 'online' ? 'success' : 'danger'; ?>">
												<?php echo e(ucfirst($server->monitoring_status)); ?>
											</span>
										</td>
										<td><?php echo $server->last_heartbeat_at ? e($server->last_heartbeat_at) : '-'; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
