<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div></div></div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-cogs mr-2"></i>Linux Services</h3></div>
				<div class="card-body">
					<div class="form-group">
						<label>SSH Config</label>
						<select id="serviceSshConfig" class="form-control">
							<?php foreach ($configs as $config): ?>
								<option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?> (<?php echo e($config->host); ?>)</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="table-responsive">
						<table class="table table-bordered table-hover datatable">
							<thead>
								<tr>
									<th>Service</th>
									<th>Controls</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($services as $service): ?>
									<tr>
										<td><strong><?php echo e($service); ?></strong></td>
										<td>
											<?php foreach ($actions as $action): ?>
												<?php echo form_open('service_manager/action', array('class' => 'd-inline service-action-form confirm-form', 'data-confirm' => ucfirst($action).' '.$service.'?')); ?>
													<input type="hidden" name="ssh_config_id" value="">
													<input type="hidden" name="service_name" value="<?php echo e($service); ?>">
													<input type="hidden" name="service_action" value="<?php echo e($action); ?>">
													<button class="btn btn-sm btn-outline-secondary mb-1"><?php echo e(ucfirst($action)); ?></button>
												<?php echo form_close(); ?>
											<?php endforeach; ?>
										</td>
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
