<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="modal-content">
	<div class="modal-header">
		<h5 class="modal-title"><i class="fas fa-key mr-2"></i><?php echo $config ? 'Edit SSH Config' : 'Add SSH Config'; ?></h5>
		<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
	</div>
	<div class="modal-body">
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
					<label>Name</label>
					<input type="text" name="name" class="form-control" value="<?php echo $config ? e($config->name) : ''; ?>" required>
				</div>
			</div>
			<div class="col-md-6">
				<div class="form-group">
					<label>Monitored Server</label>
					<select name="server_id" class="form-control">
						<option value="">Manual Host</option>
						<?php foreach ($servers as $server): ?>
							<option value="<?php echo (int) $server->id; ?>" <?php echo $config && (int) $config->server_id === (int) $server->id ? 'selected' : ''; ?>>
								<?php echo e($server->server_name); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-8">
				<div class="form-group">
					<label>Host</label>
					<input type="text" name="host" class="form-control" value="<?php echo $config ? e($config->host) : ''; ?>" required>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
					<label>Port</label>
					<input type="number" name="port" class="form-control" value="<?php echo $config ? (int) $config->port : 22; ?>" min="1" max="65535" required>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
					<label>Username</label>
					<input type="text" name="username" class="form-control" value="<?php echo $config ? e($config->username) : ''; ?>" required>
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					<label>Authentication</label>
					<select name="auth_type" class="form-control" required>
						<option value="private_key" <?php echo $config && $config->auth_type === 'private_key' ? 'selected' : ''; ?>>Private Key</option>
						<option value="password" <?php echo $config && $config->auth_type === 'password' ? 'selected' : ''; ?>>Password</option>
					</select>
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					<label>Status</label>
					<select name="status" class="form-control" required>
						<option value="active" <?php echo ! $config || $config->status === 'active' ? 'selected' : ''; ?>>Active</option>
						<option value="inactive" <?php echo $config && $config->status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
					</select>
				</div>
			</div>
		</div>
		<div class="form-group">
			<label>Password</label>
			<input type="password" name="password" class="form-control" placeholder="<?php echo $config ? 'Leave blank to keep existing password' : ''; ?>">
		</div>
		<div class="form-group">
			<label>Private Key</label>
			<textarea name="private_key" class="form-control" rows="6" placeholder="<?php echo $config ? 'Leave blank to keep existing private key' : '-----BEGIN OPENSSH PRIVATE KEY-----'; ?>"></textarea>
		</div>
		<div class="form-group mb-0">
			<label>Passphrase</label>
			<input type="password" name="passphrase" class="form-control" placeholder="<?php echo $config ? 'Leave blank to keep existing passphrase' : ''; ?>">
		</div>
	</div>
	<div class="modal-footer">
		<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
		<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
	</div>
</div>
