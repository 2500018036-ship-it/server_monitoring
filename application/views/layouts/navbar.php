<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$navbar_servers = isset($navbar_monitoring['servers']) ? $navbar_monitoring['servers'] : array();
$navbar_selected_id = isset($navbar_monitoring['selected_server_id']) ? (int) $navbar_monitoring['selected_server_id'] : 0;
$navbar_current = isset($navbar_monitoring['server']) ? $navbar_monitoring['server'] : NULL;
$navbar_status = $navbar_current && isset($navbar_current->health_status) ? $navbar_current->health_status : 'offline';
$navbar_badge = $navbar_current && isset($navbar_current->health_badge) ? $navbar_current->health_badge : 'danger';
$navbar_label = $navbar_current && isset($navbar_current->health_label) ? $navbar_current->health_label : ucfirst($navbar_status);
$navbar_summary = $navbar_current && isset($navbar_current->health_summary) ? $navbar_current->health_summary : '';
$navbar_query = $_GET;
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
	<ul class="navbar-nav">
		<li class="nav-item">
			<a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle sidebar">
				<i class="fas fa-bars"></i>
			</a>
		</li>
		<li class="nav-item d-none d-sm-inline-block">
			<a href="<?php echo site_url('dashboard'); ?>" class="nav-link">Dashboard</a>
		</li>
	</ul>

	<ul class="navbar-nav ml-auto">
		<li class="nav-item dropdown server-switcher" id="navbarServerSwitcher" data-selected-id="<?php echo (int) $navbar_selected_id; ?>">
			<a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#" role="button">
				<span id="navbarCurrentServerDot" class="server-status-dot status-<?php echo e($navbar_status); ?>"></span>
				<span class="d-none d-sm-inline server-switcher-current">
					<span id="navbarCurrentServerName"><?php echo $navbar_current ? e($navbar_current->server_name) : 'No Server'; ?></span>
					<small id="navbarCurrentServerMeta"><?php echo e($navbar_summary); ?></small>
				</span>
				<span id="navbarCurrentServerBadge" class="badge badge-<?php echo e($navbar_badge); ?> ml-2"><?php echo e($navbar_label); ?></span>
			</a>
			<div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="navbarServerList">
				<span class="dropdown-item dropdown-header">Server Switcher</span>
				<div class="dropdown-divider"></div>
				<?php if (empty($navbar_servers)): ?>
					<span class="dropdown-item text-muted">Belum ada server.</span>
				<?php endif; ?>
				<?php foreach ($navbar_servers as $nav_server): ?>
					<?php
					$item_status = isset($nav_server->health_status) ? $nav_server->health_status : 'offline';
					$item_badge = isset($nav_server->health_badge) ? $nav_server->health_badge : 'danger';
					$item_label = isset($nav_server->health_label) ? $nav_server->health_label : ucfirst($item_status);
					$item_summary = isset($nav_server->health_summary) ? $nav_server->health_summary : '';
					$navbar_query['server_id'] = (int) $nav_server->id;
					$item_url = current_url().'?'.http_build_query($navbar_query);
					?>
					<a href="<?php echo e($item_url); ?>" class="dropdown-item navbar-server-option" data-server-id="<?php echo (int) $nav_server->id; ?>">
						<div class="d-flex align-items-start">
							<span class="server-status-dot status-<?php echo e($item_status); ?> mt-1"></span>
							<div class="server-switcher-item flex-fill">
								<div class="d-flex justify-content-between align-items-center">
									<strong><?php echo e($nav_server->server_name); ?></strong>
									<span>
										<span class="badge badge-<?php echo e($item_badge); ?>"><?php echo e($item_label); ?></span>
										<?php if ((int) $nav_server->id === (int) $navbar_selected_id): ?>
											<span class="badge badge-primary">Current</span>
										<?php endif; ?>
									</span>
								</div>
								<small class="text-muted"><?php echo e($item_summary); ?></small>
							</div>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</li>
		<li class="nav-item">
			<button type="button" class="nav-link btn btn-link" id="darkModeToggle" title="Dark Mode">
				<i class="fas fa-moon"></i>
			</button>
		</li>
		<li class="nav-item dropdown">
			<a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#">
				<img src="<?php echo avatar_url($current_user['photo']); ?>" class="img-circle elevation-1 mr-2 navbar-avatar" alt="User Image">
				<span class="d-none d-md-inline"><?php echo e($current_user['fullname']); ?></span>
			</a>
			<div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
				<span class="dropdown-item dropdown-header"><?php echo e($current_user['role_name']); ?></span>
				<div class="dropdown-divider"></div>
				<a href="<?php echo site_url('profile'); ?>" class="dropdown-item">
					<i class="fas fa-user mr-2"></i> Profile
				</a>
				<div class="dropdown-divider"></div>
				<?php echo form_open('logout', array('class' => 'm-0')); ?>
					<button type="submit" class="dropdown-item dropdown-footer text-danger">
						<i class="fas fa-sign-out-alt mr-2"></i> Logout
					</button>
				<?php echo form_close(); ?>
			</div>
		</li>
	</ul>
</nav>
