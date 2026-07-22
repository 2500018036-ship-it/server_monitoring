<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div></div></div></section>
	<section class="content"><div class="container-fluid">
		<div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-clock mr-2"></i>Cron Jobs</h3></div><div class="card-body">
			<?php echo form_open('cron_manager/action', array('class' => 'confirm-form', 'data-confirm' => 'Jalankan cron action?')); ?>
				<div class="row">
					<div class="col-md-3"><select name="ssh_config_id" class="form-control" required><?php foreach ($configs as $config): ?><option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?></option><?php endforeach; ?></select></div>
					<div class="col-md-2"><select name="cron_action" class="form-control" required><option value="list">List</option><option value="add">Add</option><option value="run">Run Manual</option></select></div>
					<div class="col-md-3"><input type="text" name="cron_expression" class="form-control" placeholder="* * * * *"></div>
					<div class="col-md-3"><input type="text" name="command" class="form-control" placeholder="/usr/bin/php script.php"></div>
					<div class="col-md-1"><button class="btn btn-primary btn-block"><i class="fas fa-play"></i></button></div>
				</div>
			<?php echo form_close(); ?>
		</div></div>
		<div class="card"><div class="card-header"><h3 class="card-title">Cron History</h3></div><div class="card-body table-responsive"><table class="table table-bordered datatable datatable-export"><thead><tr><th>Action</th><th>Expression</th><th>Command</th><th>Status</th><th>Created At</th></tr></thead><tbody><?php foreach ($history as $row): ?><tr><td><?php echo e($row->action); ?></td><td><?php echo e($row->cron_expression); ?></td><td><?php echo e($row->command); ?></td><td><?php echo e($row->status); ?></td><td><?php echo e($row->created_at); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
	</div></section>
</div>
