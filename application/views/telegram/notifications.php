<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$setting = $notification_setting;
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1><?php echo e($page_title); ?></h1>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<?php echo form_open('settings/notification-settings/update'); ?>
				<div class="row">
					<div class="col-lg-5">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fas fa-sliders-h mr-2"></i>Threshold & Anti Spam</h3>
							</div>
							<div class="card-body">
								<div class="custom-control custom-switch mb-3">
									<input type="checkbox" name="enabled" value="1" class="custom-control-input" id="notificationEnabled" <?php echo (int) $setting->enabled === 1 ? 'checked' : ''; ?>>
									<label class="custom-control-label" for="notificationEnabled">Telegram Notification Active</label>
								</div>

								<div class="form-group">
									<label>CPU Threshold</label>
									<div class="input-group">
										<input type="number" name="cpu_threshold" class="form-control" min="1" max="100" step="0.01" value="<?php echo e($setting->cpu_threshold); ?>" required>
										<div class="input-group-append"><span class="input-group-text">%</span></div>
									</div>
								</div>

								<div class="form-group">
									<label>RAM Threshold</label>
									<div class="input-group">
										<input type="number" name="ram_threshold" class="form-control" min="1" max="100" step="0.01" value="<?php echo e($setting->ram_threshold); ?>" required>
										<div class="input-group-append"><span class="input-group-text">%</span></div>
									</div>
								</div>

								<div class="form-group">
									<label>Storage Threshold</label>
									<div class="input-group">
										<input type="number" name="storage_threshold" class="form-control" min="1" max="100" step="0.01" value="<?php echo e($setting->storage_threshold); ?>" required>
										<div class="input-group-append"><span class="input-group-text">%</span></div>
									</div>
								</div>

								<div class="form-group mb-0">
									<label>Cooldown Notifikasi Sama</label>
									<div class="input-group">
										<input type="number" name="cooldown_minutes" class="form-control" min="1" value="<?php echo e($setting->cooldown_minutes); ?>" required>
										<div class="input-group-append"><span class="input-group-text">menit</span></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-7">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fab fa-telegram-plane mr-2"></i>Event Notification</h3>
							</div>
							<div class="card-body p-0">
								<table class="table table-hover mb-0">
									<tbody>
										<?php foreach ($notification_events as $column => $event): ?>
											<tr>
												<td style="width: 72px;">
													<div class="custom-control custom-switch">
														<input type="checkbox" name="<?php echo e($column); ?>" value="1" class="custom-control-input" id="<?php echo e($column); ?>" <?php echo (int) $setting->$column === 1 ? 'checked' : ''; ?>>
														<label class="custom-control-label" for="<?php echo e($column); ?>"></label>
													</div>
												</td>
												<td>
													<strong><?php echo e($event['label']); ?></strong>
													<div class="text-muted small"><?php echo e($event['description']); ?></div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<div class="card-footer text-right">
								<button type="submit" class="btn btn-primary">
									<i class="fas fa-save mr-1"></i> Simpan Notification Settings
								</button>
							</div>
						</div>
					</div>
				</div>
			<?php echo form_close(); ?>
		</div>
	</section>
</div>
