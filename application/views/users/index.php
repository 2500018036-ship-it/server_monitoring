<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6">
					<h1><?php echo e($page_title); ?></h1>
				</div>
				<div class="col-sm-6 text-sm-right">
					<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateUser">
						<i class="fas fa-plus mr-1"></i> Tambah User
					</button>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-users mr-2"></i>Data Users</h3>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-bordered table-hover datatable">
							<thead>
								<tr>
									<th style="width: 48px;">Foto</th>
									<th>Nama</th>
									<th>Username</th>
									<th>Email</th>
									<th>Role</th>
									<th>Status</th>
									<th>Last Login</th>
									<th style="width: 145px;">Aksi</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($users as $item): ?>
									<tr>
										<td><img src="<?php echo avatar_url($item->photo); ?>" class="table-avatar" alt="Avatar"></td>
										<td><?php echo e($item->fullname); ?></td>
										<td><?php echo e($item->username); ?></td>
										<td><?php echo e($item->email); ?></td>
										<td><span class="badge <?php echo role_badge_class($item->role_name); ?>"><?php echo e($item->role_name); ?></span></td>
										<td>
											<span class="badge <?php echo $item->status === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
												<?php echo e(ucfirst($item->status)); ?>
											</span>
										</td>
										<td><?php echo $item->last_login ? e($item->last_login) : '-'; ?></td>
										<td>
											<button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#modalEditUser<?php echo (int) $item->id; ?>" title="Edit">
												<i class="fas fa-edit"></i>
											</button>
											<?php echo form_open('users/toggle/'.$item->id, array('class' => 'd-inline confirm-form', 'data-confirm' => 'Ubah status user ini?')); ?>
												<button type="submit" class="btn btn-sm btn-warning" title="Aktif / Nonaktif" <?php echo (int) $item->id === (int) $current_user['id'] ? 'disabled' : ''; ?>>
													<i class="fas fa-power-off"></i>
												</button>
											<?php echo form_close(); ?>
											<?php echo form_open('users/delete/'.$item->id, array('class' => 'd-inline confirm-form', 'data-confirm' => 'Hapus user ini?')); ?>
												<button type="submit" class="btn btn-sm btn-danger" title="Hapus" <?php echo (int) $item->id === (int) $current_user['id'] ? 'disabled' : ''; ?>>
													<i class="fas fa-trash"></i>
												</button>
											<?php echo form_close(); ?>
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

<div class="modal fade" id="modalCreateUser" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<?php echo form_open_multipart('users/store'); ?>
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Tambah User</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Fullname</label>
								<input type="text" name="fullname" class="form-control" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Username</label>
								<input type="text" name="username" class="form-control" required>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Email</label>
								<input type="email" name="email" class="form-control" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Password</label>
								<input type="password" name="password" class="form-control" required minlength="6">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Role</label>
								<select name="role_id" class="form-control" required>
									<?php foreach ($roles as $role): ?>
										<option value="<?php echo (int) $role->id; ?>"><?php echo e($role->role_name); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Status</label>
								<select name="status" class="form-control" required>
									<option value="active">Active</option>
									<option value="inactive">Inactive</option>
								</select>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label>Foto Profil</label>
						<input type="file" name="photo" class="form-control-file" accept="image/*">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
				</div>
			</div>
		<?php echo form_close(); ?>
	</div>
</div>

<?php foreach ($users as $item): ?>
	<div class="modal fade" id="modalEditUser<?php echo (int) $item->id; ?>" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<?php echo form_open_multipart('users/update/'.$item->id); ?>
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title"><i class="fas fa-user-edit mr-2"></i>Edit User</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="row align-items-center mb-3">
							<div class="col-auto">
								<img src="<?php echo avatar_url($item->photo); ?>" class="modal-avatar" alt="Avatar">
							</div>
							<div class="col">
								<strong><?php echo e($item->fullname); ?></strong>
								<div class="text-muted"><?php echo e($item->email); ?></div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label>Fullname</label>
									<input type="text" name="fullname" class="form-control" value="<?php echo e($item->fullname); ?>" required>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Username</label>
									<input type="text" name="username" class="form-control" value="<?php echo e($item->username); ?>" required>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label>Email</label>
									<input type="email" name="email" class="form-control" value="<?php echo e($item->email); ?>" required>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Password Baru</label>
									<input type="password" name="password" class="form-control" minlength="6">
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label>Role</label>
									<select name="role_id" class="form-control" required <?php echo (int) $item->id === (int) $current_user['id'] ? 'disabled' : ''; ?>>
										<?php foreach ($roles as $role): ?>
											<option value="<?php echo (int) $role->id; ?>" <?php echo (int) $role->id === (int) $item->role_id ? 'selected' : ''; ?>>
												<?php echo e($role->role_name); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<?php if ((int) $item->id === (int) $current_user['id']): ?>
										<input type="hidden" name="role_id" value="<?php echo (int) $item->role_id; ?>">
									<?php endif; ?>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Status</label>
									<select name="status" class="form-control" required <?php echo (int) $item->id === (int) $current_user['id'] ? 'disabled' : ''; ?>>
										<option value="active" <?php echo $item->status === 'active' ? 'selected' : ''; ?>>Active</option>
										<option value="inactive" <?php echo $item->status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
									</select>
									<?php if ((int) $item->id === (int) $current_user['id']): ?>
										<input type="hidden" name="status" value="active">
									<?php endif; ?>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label>Foto Profil</label>
							<input type="file" name="photo" class="form-control-file" accept="image/*">
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
						<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
					</div>
				</div>
			<?php echo form_close(); ?>
		</div>
	</div>
<?php endforeach; ?>
