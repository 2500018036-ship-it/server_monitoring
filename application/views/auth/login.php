<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $app_name = isset($app_setting->app_name) && $app_setting->app_name ? $app_setting->app_name : 'Server Monitoring'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo e($app_name); ?> | Login</title>
	<link rel="icon" href="<?php echo base_url('assets/img/favicon.svg'); ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
	<link rel="stylesheet" href="<?php echo base_url('assets/css/app.css'); ?>">
</head>
<body class="hold-transition login-page">
<div class="login-box">
	<div class="login-logo">
		<a href="<?php echo site_url('login'); ?>"><i class="fas fa-server mr-2"></i><?php echo e($app_name); ?></a>
	</div>
	<div class="card">
		<div class="card-body login-card-body">
			<p class="login-box-msg">Masuk untuk membuka dashboard</p>

			<?php echo form_open('login'); ?>
				<div class="input-group mb-3">
					<input type="text" name="identity" class="form-control" placeholder="Username / Email" required autofocus>
					<div class="input-group-append">
						<div class="input-group-text"><span class="fas fa-user"></span></div>
					</div>
				</div>
				<div class="input-group mb-3">
					<input type="password" name="password" class="form-control" placeholder="Password" required>
					<div class="input-group-append">
						<div class="input-group-text"><span class="fas fa-lock"></span></div>
					</div>
				</div>
				<div class="row">
					<div class="col-7">
						<div class="icheck-primary">
							<input type="checkbox" id="remember" name="remember" value="1">
							<label for="remember">Remember Me</label>
						</div>
					</div>
					<div class="col-5">
						<button type="submit" class="btn btn-primary btn-block">
							<i class="fas fa-sign-in-alt mr-1"></i> Login
						</button>
					</div>
				</div>
			<?php echo form_close(); ?>

			<p class="mb-1 mt-3">
				<a href="<?php echo site_url('forgot-password'); ?>">Forgot Password</a>
			</p>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	window.SM_FLASH = {
		success: <?php echo json_encode($this->session->flashdata('success')); ?>,
		error: <?php echo json_encode($this->session->flashdata('error')); ?>
	};
</script>
<script src="<?php echo base_url('assets/js/app.js'); ?>"></script>
</body>
</html>
