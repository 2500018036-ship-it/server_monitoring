<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div></div></div></section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-archive mr-2"></i>Manual Backup</h3></div>
				<div class="card-body">
					<?php echo form_open('backup_manager/run', array('class' => 'confirm-form', 'data-confirm' => 'Jalankan backup manual?')); ?>
						<div class="row">
							<div class="col-md-3"><select name="ssh_config_id" class="form-control" required><?php foreach ($configs as $config): ?><option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?></option><?php endforeach; ?></select></div>
							<div class="col-md-3"><select name="backup_type" class="form-control" required><option value="website">Website</option><option value="database">Database</option><option value="configuration">Configuration</option><option value="docker_volume">Docker Volume</option></select></div>
							<div class="col-md-4"><input type="text" name="source_path" class="form-control" placeholder="/var/www/html or database_name" required></div>
							<div class="col-md-2"><button class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i> Backup</button></div>
						</div>
					<?php echo form_close(); ?>
				</div>
			</div>
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-2"></i>Backup History</h3></div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover datatable datatable-export">
						<thead><tr><th>Type</th><th>Action</th><th>Remote Path</th><th>Status</th><th>Created At</th></tr></thead>
						<tbody><?php foreach ($history as $row): ?><tr><td><?php echo e($row->backup_type); ?></td><td><?php echo e($row->action); ?></td><td><?php echo e($row->remote_path); ?></td><td><?php echo e($row->status); ?></td><td><?php echo e($row->created_at); ?></td></tr><?php endforeach; ?></tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
