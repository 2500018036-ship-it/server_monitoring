<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div></div></div></section>
	<section class="content"><div class="container-fluid">
		<div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-certificate mr-2"></i>Let's Encrypt</h3></div><div class="card-body">
			<?php echo form_open('ssl_manager/action', array('class' => 'confirm-form', 'data-confirm' => 'Jalankan SSL action?')); ?>
				<div class="row">
					<div class="col-md-3"><select name="ssh_config_id" class="form-control" required><?php foreach ($configs as $config): ?><option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?></option><?php endforeach; ?></select></div>
					<div class="col-md-3"><input type="text" name="domain" class="form-control" placeholder="example.com" required></div>
					<div class="col-md-3"><select name="ssl_action" class="form-control"><option value="check">Check SSL</option><option value="renew">Renew SSL</option><option value="force_renew">Force Renew</option></select></div>
					<div class="col-md-3"><button class="btn btn-primary btn-block"><i class="fas fa-play mr-1"></i> Run</button></div>
				</div>
			<?php echo form_close(); ?>
		</div></div>
		<div class="card"><div class="card-header"><h3 class="card-title">SSL History</h3></div><div class="card-body table-responsive"><table class="table table-bordered datatable datatable-export"><thead><tr><th>Domain</th><th>Action</th><th>Status</th><th>Auto Renew</th><th>Created At</th></tr></thead><tbody><?php foreach ($history as $row): ?><tr><td><?php echo e($row->domain); ?></td><td><?php echo e($row->action); ?></td><td><?php echo e($row->status); ?></td><td><?php echo e($row->auto_renew_status); ?></td><td><?php echo e($row->created_at); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
	</div></section>
</div>
