<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$status = isset($telegram_setting->status) ? $telegram_setting->status : 'disconnected';
$status_badges = array(
	'connected' => 'success',
	'failed' => 'danger',
	'disconnected' => 'secondary',
);
$status_labels = array(
	'connected' => 'Connected',
	'failed' => 'Failed',
	'disconnected' => 'Disconnected',
);
$status_class = isset($status_badges[$status]) ? $status_badges[$status] : 'secondary';
$status_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
$selected_chat_id = isset($telegram_setting->selected_chat_id) ? (string) $telegram_setting->selected_chat_id : '';
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
			<div class="row">
				<div class="col-lg-5">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fab fa-telegram-plane mr-2"></i>Bot Connection</h3>
							<div class="card-tools">
								<span class="badge badge-<?php echo e($status_class); ?>"><?php echo e($status_label); ?></span>
							</div>
						</div>
						<div class="card-body">
							<table class="table table-sm">
								<tr>
									<th style="width: 150px;">Bot Name</th>
									<td><?php echo e(isset($telegram_setting->bot_name) && $telegram_setting->bot_name ? $telegram_setting->bot_name : '-'); ?></td>
								</tr>
								<tr>
									<th>Username</th>
									<td><?php echo isset($telegram_setting->bot_username) && $telegram_setting->bot_username ? '@'.e($telegram_setting->bot_username) : '-'; ?></td>
								</tr>
								<tr>
									<th>Bot ID</th>
									<td><?php echo e(isset($telegram_setting->bot_id) && $telegram_setting->bot_id ? $telegram_setting->bot_id : '-'); ?></td>
								</tr>
								<tr>
									<th>Token</th>
									<td><code><?php echo e($masked_token); ?></code></td>
								</tr>
								<tr>
									<th>Connected At</th>
									<td><?php echo e(isset($telegram_setting->connected_at) && $telegram_setting->connected_at ? $telegram_setting->connected_at : '-'); ?></td>
								</tr>
								<?php if (isset($telegram_setting->last_error) && $telegram_setting->last_error): ?>
									<tr>
										<th>Last Error</th>
										<td class="text-danger"><?php echo e($telegram_setting->last_error); ?></td>
									</tr>
								<?php endif; ?>
							</table>

							<hr>

							<?php echo form_open('settings/telegram-bot/connect'); ?>
								<div class="form-group">
									<label>Bot Token</label>
									<input type="password" name="bot_token" class="form-control" placeholder="123456789:AA..." autocomplete="off" required>
								</div>
								<button type="submit" class="btn btn-primary">
									<i class="fas fa-plug mr-1"></i> Connect Bot
								</button>
							<?php echo form_close(); ?>
						</div>
					</div>

					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fas fa-paper-plane mr-2"></i>Test Message</h3>
						</div>
						<div class="card-body">
							<table class="table table-sm">
								<tr>
									<th style="width: 150px;">Selected Chat</th>
									<td>
										<?php if ($selected_chat_id !== ''): ?>
											<?php echo e($telegram_setting->selected_chat_name ?: $selected_chat_id); ?>
											<div class="text-muted small"><?php echo e($selected_chat_id); ?></div>
										<?php else: ?>
											-
										<?php endif; ?>
									</td>
								</tr>
								<tr>
									<th>Chat Type</th>
									<td><?php echo e(isset($telegram_setting->selected_chat_type) && $telegram_setting->selected_chat_type ? $telegram_setting->selected_chat_type : '-'); ?></td>
								</tr>
								<tr>
									<th>Last Test</th>
									<td><?php echo e(isset($telegram_setting->last_test_at) && $telegram_setting->last_test_at ? $telegram_setting->last_test_at : '-'); ?></td>
								</tr>
							</table>

							<?php echo form_open('settings/telegram-bot/test-message'); ?>
								<div class="form-group">
									<label>Message</label>
									<textarea name="message" class="form-control" rows="3">Test message dari Server Monitoring.</textarea>
								</div>
								<button type="submit" class="btn btn-success" <?php echo $selected_chat_id === '' ? 'disabled' : ''; ?>>
									<i class="fas fa-paper-plane mr-1"></i> Test Message
								</button>
							<?php echo form_close(); ?>
						</div>
					</div>
				</div>

				<div class="col-lg-7">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fas fa-comments mr-2"></i>Chat ID</h3>
							<div class="card-tools">
								<?php echo form_open('settings/telegram-bot/get-chat-id', array('class' => 'd-inline')); ?>
									<button type="submit" class="btn btn-sm btn-info">
										<i class="fas fa-sync-alt mr-1"></i> Get Chat ID
									</button>
								<?php echo form_close(); ?>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table class="table table-bordered table-hover datatable">
									<thead>
										<tr>
											<th>Chat ID</th>
											<th>Name</th>
											<th>Username</th>
											<th>Type</th>
											<th>Last Message</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($chats as $chat): ?>
											<tr>
												<td><code><?php echo e($chat->chat_id); ?></code></td>
												<td><?php echo e($chat->chat_name ?: '-'); ?></td>
												<td><?php echo $chat->chat_username ? '@'.e($chat->chat_username) : '-'; ?></td>
												<td><span class="badge badge-light"><?php echo e($chat->chat_type ?: '-'); ?></span></td>
												<td><?php echo e($chat->last_message_at ?: '-'); ?></td>
												<td>
													<?php if ((string) $chat->chat_id === $selected_chat_id): ?>
														<span class="badge badge-success">Current</span>
													<?php else: ?>
														<?php echo form_open('settings/telegram-bot/select-chat', array('class' => 'd-inline')); ?>
															<input type="hidden" name="chat_id" value="<?php echo e($chat->chat_id); ?>">
															<button type="submit" class="btn btn-sm btn-primary">
																<i class="fas fa-check mr-1"></i> Select
															</button>
														<?php echo form_close(); ?>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>

							<?php if (isset($telegram_setting->last_chat_sync_at) && $telegram_setting->last_chat_sync_at): ?>
								<div class="text-muted small mt-2">Last Sync: <?php echo e($telegram_setting->last_chat_sync_at); ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
