<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div></div></div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3></div>
				<div class="card-body">
					<div class="form-group">
						<label>SSH Config</label>
						<select id="quickSshConfig" class="form-control">
							<?php foreach ($configs as $config): ?>
								<option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?> (<?php echo e($config->host); ?>)</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="row">
						<?php foreach ($actions as $key => $action): ?>
							<?php if (has_role($action['roles'])): ?>
								<div class="col-lg-3 col-md-4 col-sm-6 mb-3">
									<?php echo form_open('quick_actions/run', array('class' => 'quick-action-form confirm-form', 'data-confirm' => 'Jalankan '.$action['label'].'?')); ?>
										<input type="hidden" name="ssh_config_id" value="">
										<input type="hidden" name="action_key" value="<?php echo e($key); ?>">
										<button class="btn btn-outline-primary btn-block quick-action-button">
											<i class="<?php echo e($action['icon']); ?> mb-2"></i>
											<span><?php echo e($action['label']); ?></span>
										</button>
									<?php echo form_close(); ?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
