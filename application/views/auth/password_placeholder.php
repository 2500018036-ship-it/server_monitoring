<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $app_name = isset($app_setting->app_name) && $app_setting->app_name ? $app_setting->app_name : 'Server Monitoring'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo e($app_name); ?> | <?php echo e($page_title); ?></title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="<?php echo base_url('assets/css/app.css'); ?>">
</head>
<body class="hold-transition login-page">
<div class="login-box">
	<div class="card">
		<div class="card-body login-card-body text-center">
			<i class="fas fa-key fa-3x text-primary mb-3"></i>
			<h4><?php echo e($title); ?></h4>
			<p class="text-muted"><?php echo e($message); ?></p>
			<a href="<?php echo site_url('login'); ?>" class="btn btn-primary btn-block">
				<i class="fas fa-arrow-left mr-1"></i> Kembali ke Login
			</a>
		</div>
	</div>
</div>
</body>
</html>
