<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div></div></div></section>
	<section class="content"><div class="container-fluid">
		<div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-shield-alt mr-2"></i>Firewall Controls</h3></div><div class="card-body">
			<?php echo form_open('firewall_manager/action', array('class' => 'confirm-form', 'data-confirm' => 'Jalankan firewall action?')); ?>
				<div class="row">
					<div class="col-md-3"><select name="ssh_config_id" class="form-control" required><?php foreach ($configs as $config): ?><option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?></option><?php endforeach; ?></select></div>
					<div class="col-md-2"><select name="firewall_type" class="form-control"><option value="ufw">UFW</option><option value="iptables">iptables</option></select></div>
					<div class="col-md-3"><select name="firewall_action" class="form-control"><option value="status">View Rules</option><option value="enable">Enable</option><option value="disable">Disable</option><option value="allow_port">Allow Port</option><option value="deny_port">Deny Port</option><option value="allow_ip">Allow IP</option><option value="block_ip">Block IP</option></select></div>
					<div class="col-md-3"><input type="text" name="rule_value" class="form-control" placeholder="22/tcp or 1.2.3.4"></div>
					<div class="col-md-1"><button class="btn btn-primary btn-block"><i class="fas fa-play"></i></button></div>
				</div>
			<?php echo form_close(); ?>
		</div></div>
		<div class="card"><div class="card-header"><h3 class="card-title">Firewall History</h3></div><div class="card-body table-responsive"><table class="table table-bordered datatable datatable-export"><thead><tr><th>Type</th><th>Action</th><th>Rule</th><th>Status</th><th>Created At</th></tr></thead><tbody><?php foreach ($history as $row): ?><tr><td><?php echo e($row->firewall_type); ?></td><td><?php echo e($row->action); ?></td><td><?php echo e($row->rule); ?></td><td><?php echo e($row->status); ?></td><td><?php echo e($row->created_at); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
	</div></section>
</div>
