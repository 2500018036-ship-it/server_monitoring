<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6 text-right">
					<a href="<?php echo site_url('database'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Database</a>
				</div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-search mr-2"></i>Databases</h3></div>
				<div class="card-body">
					<form method="get" action="<?php echo site_url('database/explorer'); ?>" class="mb-3">
						<div class="row">
							<div class="col-md-4">
								<select name="ssh_config_id" class="form-control" onchange="this.form.submit()">
									<?php foreach ($configs as $config): ?>
										<option value="<?php echo (int) $config->id; ?>" <?php echo $selected_config && (int) $selected_config->id === (int) $config->id ? 'selected' : ''; ?>><?php echo e($config->name); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</form>
					<?php if (empty($result['ok'])): ?>
						<div class="alert alert-danger"><?php echo e(isset($result['message']) ? $result['message'] : 'Database tidak bisa dibaca.'); ?></div>
					<?php endif; ?>
					<table class="table table-bordered table-striped datatable datatable-export">
						<thead><tr><th>Database</th><th>Action</th></tr></thead>
						<tbody>
							<?php foreach (isset($result['databases']) ? $result['databases'] : array() as $database): ?>
								<tr>
									<td><?php echo e($database); ?></td>
									<td>
										<a class="btn btn-sm btn-primary" href="<?php echo site_url('database/tables?database='.rawurlencode($database).'&ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0)); ?>">
											<i class="fas fa-table mr-1"></i> Tables
										</a>
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
