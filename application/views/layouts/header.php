<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$app_name = isset($app_setting->app_name) && $app_setting->app_name ? $app_setting->app_name : 'Server Monitoring';
$favicon = isset($app_setting->favicon) && $app_setting->favicon ? uploaded_asset_url($app_setting->favicon, 'assets/img/favicon.svg') : base_url('assets/img/favicon.svg');
$app_css_version = file_exists(FCPATH.'assets/css/app.css') ? filemtime(FCPATH.'assets/css/app.css') : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo e($app_name); ?> | <?php echo e($page_title); ?></title>
	<link rel="icon" href="<?php echo $favicon; ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.2/css/buttons.bootstrap4.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/material-darker.min.css">
	<link rel="stylesheet" href="<?php echo base_url('assets/css/app.css?v='.$app_css_version); ?>">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
