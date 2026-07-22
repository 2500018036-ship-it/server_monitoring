<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$timezones = array('Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC', 'Asia/Singapore');
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
			<?php echo form_open_multipart('settings/update'); ?>
				<div class="row">
					<div class="col-lg-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fas fa-sliders-h mr-2"></i>Aplikasi</h3>
							</div>
							<div class="card-body">
								<div class="form-group">
									<label>Nama Aplikasi</label>
									<input type="text" name="app_name" class="form-control" value="<?php echo e($setting->app_name); ?>" required>
								</div>
								<div class="form-group">
									<label>Logo</label>
									<input type="file" name="logo" class="form-control-file" accept="image/*">
									<?php if ($setting->logo): ?>
										<div class="mt-2"><img src="<?php echo uploaded_asset_url($setting->logo, 'assets/img/favicon.svg'); ?>" class="settings-preview" alt="Logo"></div>
									<?php endif; ?>
								</div>
								<div class="form-group">
									<label>Favicon</label>
									<input type="file" name="favicon" class="form-control-file" accept=".ico,image/*">
									<?php if ($setting->favicon): ?>
										<div class="mt-2"><img src="<?php echo uploaded_asset_url($setting->favicon, 'assets/img/favicon.svg'); ?>" class="settings-preview" alt="Favicon"></div>
									<?php endif; ?>
								</div>
								<div class="form-group">
									<label>Timezone</label>
									<select name="timezone" class="form-control" required>
										<?php foreach ($timezones as $timezone): ?>
											<option value="<?php echo e($timezone); ?>" <?php echo $setting->timezone === $timezone ? 'selected' : ''; ?>>
												<?php echo e($timezone); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="form-group mb-0">
									<label>Monitoring Interval</label>
									<div class="input-group">
										<input type="number" name="monitoring_interval" class="form-control" min="1" max="3" value="<?php echo e($setting->monitoring_interval); ?>" required>
										<div class="input-group-append"><span class="input-group-text">detik</span></div>
									</div>
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fas fa-envelope mr-2"></i>SMTP</h3>
							</div>
							<div class="card-body">
								<div class="row">
									<div class="col-md-8">
										<div class="form-group">
											<label>SMTP Host</label>
											<input type="text" name="smtp_host" class="form-control" value="<?php echo e($setting->smtp_host); ?>">
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group">
											<label>SMTP Port</label>
											<input type="number" name="smtp_port" class="form-control" value="<?php echo e($setting->smtp_port); ?>">
										</div>
									</div>
								</div>
								<div class="form-group">
									<label>SMTP User</label>
									<input type="text" name="smtp_user" class="form-control" value="<?php echo e($setting->smtp_user); ?>">
								</div>
								<div class="form-group mb-0">
									<label>SMTP Password</label>
									<input type="password" name="smtp_password" class="form-control" value="<?php echo e($setting->smtp_password); ?>">
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fab fa-telegram-plane mr-2"></i>Telegram</h3>
							</div>
							<div class="card-body">
								<div class="form-group">
									<label>Telegram Bot Token</label>
									<input type="text" name="telegram_bot_token" class="form-control" value="<?php echo e($setting->telegram_bot_token); ?>">
								</div>
								<div class="form-group mb-0">
									<label>Telegram Chat ID</label>
									<input type="text" name="telegram_chat_id" class="form-control" value="<?php echo e($setting->telegram_chat_id); ?>">
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fas fa-robot mr-2"></i>AI Provider</h3>
							</div>
							<div class="card-body">
								<div class="form-group">
									<label>OpenAI API Key</label>
									<input type="password" name="openai_api_key" class="form-control" value="<?php echo e($setting->openai_api_key); ?>">
								</div>
								<div class="form-group">
									<label>Gemini API Key</label>
									<input type="password" name="gemini_api_key" class="form-control" value="<?php echo e($setting->gemini_api_key); ?>">
								</div>
								<div class="form-group mb-0">
									<label>Ollama URL</label>
									<input type="url" name="ollama_url" class="form-control" value="<?php echo e($setting->ollama_url); ?>" placeholder="http://localhost:11434">
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fas fa-key mr-2"></i>Monitoring API</h3>
							</div>
							<div class="card-body">
								<div class="form-group">
									<label>Agent API Key</label>
									<input type="text" name="agent_api_key" class="form-control" value="<?php echo e(isset($setting->agent_api_key) ? $setting->agent_api_key : ''); ?>" required>
								</div>
								<div class="form-group">
									<label>Allowed Origins</label>
									<textarea name="api_allowed_origins" class="form-control" rows="3" placeholder="https://monitoring.example.com"><?php echo e(isset($setting->api_allowed_origins) ? $setting->api_allowed_origins : ''); ?></textarea>
								</div>
								<div class="form-group mb-0">
									<label>Rate Limit</label>
									<div class="input-group">
										<input type="number" name="api_rate_limit_per_minute" class="form-control" min="1" value="<?php echo e(isset($setting->api_rate_limit_per_minute) ? $setting->api_rate_limit_per_minute : 1000); ?>" required>
										<div class="input-group-append"><span class="input-group-text">request / menit</span></div>
									</div>
								</div>
							</div>
						</div>

						<div class="text-right mb-4">
							<button type="submit" class="btn btn-primary">
								<i class="fas fa-save mr-1"></i> Simpan Settings
							</button>
						</div>
					</div>
				</div>
			<?php echo form_close(); ?>
		</div>
	</section>
</div>
