<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
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
				<div class="col-lg-4">
					<div class="card card-primary card-outline">
						<div class="card-body box-profile">
							<div class="text-center">
								<img class="profile-user-img img-fluid img-circle" src="<?php echo avatar_url($user->photo); ?>" alt="User profile picture">
							</div>
							<h3 class="profile-username text-center"><?php echo e($user->fullname); ?></h3>
							<p class="text-muted text-center"><?php echo e($user->role_name); ?></p>
							<ul class="list-group list-group-unbordered mb-3">
								<li class="list-group-item">
									<b>Username</b> <span class="float-right"><?php echo e($user->username); ?></span>
								</li>
								<li class="list-group-item">
									<b>Email</b> <span class="float-right"><?php echo e($user->email); ?></span>
								</li>
								<li class="list-group-item">
									<b>Last Login</b> <span class="float-right"><?php echo $user->last_login ? e($user->last_login) : '-'; ?></span>
								</li>
							</ul>
						</div>
					</div>
				</div>

				<div class="col-lg-8">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fas fa-user-edit mr-2"></i>Edit Profile</h3>
						</div>
						<?php echo form_open_multipart('profile/update'); ?>
							<div class="card-body">
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label>Fullname</label>
											<input type="text" name="fullname" class="form-control" value="<?php echo e($user->fullname); ?>" required>
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group">
											<label>Email</label>
											<input type="email" name="email" class="form-control" value="<?php echo e($user->email); ?>" required>
										</div>
									</div>
								</div>
								<div class="form-group mb-0">
									<label>Foto Profil</label>
									<input type="file" name="photo" class="form-control-file" accept="image/*">
								</div>
							</div>
							<div class="card-footer text-right">
								<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Profile</button>
							</div>
						<?php echo form_close(); ?>
					</div>

					<div class="card">
						<div class="card-header">
							<h3 class="card-title"><i class="fas fa-key mr-2"></i>Change Password</h3>
						</div>
						<?php echo form_open('profile/change-password'); ?>
							<div class="card-body">
								<div class="form-group">
									<label>Password Saat Ini</label>
									<input type="password" name="current_password" class="form-control" required>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label>Password Baru</label>
											<input type="password" name="new_password" class="form-control" required minlength="6">
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group">
											<label>Konfirmasi Password</label>
											<input type="password" name="confirm_password" class="form-control" required minlength="6">
										</div>
									</div>
								</div>
							</div>
							<div class="card-footer text-right">
								<button type="submit" class="btn btn-primary"><i class="fas fa-lock mr-1"></i> Ganti Password</button>
							</div>
						<?php echo form_close(); ?>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
