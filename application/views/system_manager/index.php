<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div></div></div></section>
	<section class="content"><div class="container-fluid">
		<div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-tools mr-2"></i>System Actions</h3></div><div class="card-body">
			<?php echo form_open('system_manager/action', array('class' => 'confirm-form', 'data-confirm' => 'Jalankan system action?')); ?>
				<div class="row">
					<div class="col-md-4"><select name="ssh_config_id" class="form-control" required><?php foreach ($configs as $config): ?><option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?></option><?php endforeach; ?></select></div>
					<div class="col-md-4"><select name="system_action" class="form-control"><option value="info">OS / Disk / USB / CPU / Memory Info</option><option value="update">Update Package</option><option value="upgrade">Upgrade Package</option><option value="clean_cache">Clean Package Cache</option><?php if (has_role(array('Super Admin'))): ?><option value="reboot">Reboot</option><option value="shutdown">Shutdown</option><?php endif; ?></select></div>
					<div class="col-md-4"><button class="btn btn-primary btn-block"><i class="fas fa-play mr-1"></i> Run</button></div>
				</div>
			<?php echo form_close(); ?>
		</div></div>
	</div></section>
</div>
