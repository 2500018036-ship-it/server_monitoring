<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-8"><h1>Tables: <?php echo e($database); ?></h1></div>
				<div class="col-sm-4 text-right">
					<a href="<?php echo site_url('database/explorer?ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0)); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Databases</a>
				</div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-table mr-2"></i>Table Explorer</h3></div>
				<div class="card-body table-responsive">
					<?php if (empty($result['ok'])): ?>
						<div class="alert alert-danger"><?php echo e(isset($result['message']) ? $result['message'] : 'Table tidak bisa dibaca.'); ?></div>
					<?php endif; ?>
					<table class="table table-bordered table-striped datatable datatable-export">
						<thead><tr><th>Nama Tabel</th><th>Jumlah Record</th><th>Engine</th><th>Size</th><th>Collation</th><th>Created Time</th><th>Updated Time</th><th>Action</th></tr></thead>
						<tbody>
							<?php foreach (isset($result['tables']) ? $result['tables'] : array() as $table): ?>
								<tr>
									<td><?php echo e($table['table_name']); ?></td>
									<td><?php echo e($table['records']); ?></td>
									<td><?php echo e($table['engine']); ?></td>
									<td><?php echo e($table['size_mb']); ?> MB</td>
									<td><?php echo e($table['collation_name']); ?></td>
									<td><?php echo e($table['created_time']); ?></td>
									<td><?php echo e($table['updated_time']); ?></td>
									<td class="text-nowrap">
										<a href="<?php echo site_url('database/table-detail?database='.rawurlencode($database).'&table='.rawurlencode($table['table_name']).'&ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0)); ?>" class="btn btn-sm btn-info"><i class="fas fa-info-circle"></i></a>
										<a href="<?php echo site_url('database/data?database='.rawurlencode($database).'&table='.rawurlencode($table['table_name']).'&ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0)); ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
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
