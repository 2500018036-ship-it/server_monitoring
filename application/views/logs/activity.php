<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1><?php echo e($page_title); ?></h1>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-clipboard-list mr-2"></i>Activity Logs</h3>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-bordered table-hover datatable datatable-export">
							<thead>
								<tr>
									<th>User</th>
									<th>Activity</th>
									<th>IP Address</th>
									<th>Created At</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($logs as $log): ?>
									<tr>
										<td>
											<?php echo $log->fullname ? e($log->fullname) : 'System'; ?>
											<?php if ($log->username): ?>
												<div class="text-muted small">@<?php echo e($log->username); ?></div>
											<?php endif; ?>
										</td>
										<td><?php echo e($log->activity); ?></td>
										<td><?php echo e($log->ip_address); ?></td>
										<td><?php echo e($log->created_at); ?></td>
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
