<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-8"><h1><?php echo e($database.'.'.$table); ?></h1></div>
				<div class="col-sm-4 text-right">
					<a href="<?php echo site_url('database/tables?database='.rawurlencode($database).'&ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0)); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Tables</a>
					<a href="<?php echo site_url('database/data?database='.rawurlencode($database).'&table='.rawurlencode($table).'&ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0)); ?>" class="btn btn-primary"><i class="fas fa-eye mr-1"></i> Data</a>
				</div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-columns mr-2"></i>Struktur Tabel</h3></div>
				<div class="card-body table-responsive">
					<?php if (empty($result['ok'])): ?>
						<div class="alert alert-danger"><?php echo e(isset($result['message']) ? $result['message'] : 'Struktur tabel tidak bisa dibaca.'); ?></div>
					<?php endif; ?>
					<table class="table table-bordered table-striped datatable datatable-export">
						<thead>
							<tr>
								<th>Nama Kolom</th>
								<th>Tipe Data</th>
								<th>Length</th>
								<th>Primary Key</th>
								<th>Foreign Key</th>
								<th>Default Value</th>
								<th>Nullable</th>
								<th>Auto Increment</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach (isset($result['columns']) ? $result['columns'] : array() as $column): ?>
								<tr>
									<td><?php echo e($column['column_name']); ?></td>
									<td><?php echo e($column['column_type']); ?></td>
									<td><?php echo e($column['length_value']); ?></td>
									<td><?php echo $column['column_key'] === 'PRI' ? '<span class="badge badge-primary">PK</span>' : '-'; ?></td>
									<td><?php echo $column['referenced_table'] ? e($column['referenced_table'].'.'.$column['referenced_column']) : '-'; ?></td>
									<td><?php echo e($column['default_value']); ?></td>
									<td><?php echo e($column['nullable']); ?></td>
									<td><?php echo strpos($column['extra'], 'auto_increment') !== FALSE ? '<span class="badge badge-success">Yes</span>' : '-'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
