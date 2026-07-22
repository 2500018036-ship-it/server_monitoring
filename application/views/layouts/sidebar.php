<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $app_name = isset($app_setting->app_name) && $app_setting->app_name ? $app_setting->app_name : 'Server Monitoring'; ?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
	<a href="<?php echo site_url('dashboard'); ?>" class="brand-link">
		<i class="fas fa-server brand-image ml-3 mt-1"></i>
		<span class="brand-text font-weight-light ml-2"><?php echo e($app_name); ?></span>
	</a>

	<div class="sidebar">
		<div class="user-panel mt-3 pb-3 mb-3 d-flex">
			<div class="image">
				<img src="<?php echo avatar_url($current_user['photo']); ?>" class="img-circle elevation-2" alt="User Image">
			</div>
			<div class="info">
				<a href="<?php echo site_url('profile'); ?>" class="d-block"><?php echo e($current_user['fullname']); ?></a>
				<span class="badge <?php echo role_badge_class($current_user['role_name']); ?>"><?php echo e($current_user['role_name']); ?></span>
			</div>
		</div>

		<nav class="mt-2">
			<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
				<?php foreach (sidebar_menus() as $menu): ?>
					<?php if (has_role($menu['roles'])): ?>
						<?php $children = isset($menu['children']) ? array_filter($menu['children'], 'has_role_menu') : array(); ?>
						<?php if ( ! empty($children)): ?>
							<?php $is_open = is_open_menu($menu); ?>
							<li class="nav-item has-treeview <?php echo $is_open ? 'menu-open' : ''; ?>">
								<a href="#" class="nav-link <?php echo $is_open ? 'active' : ''; ?>">
									<i class="nav-icon <?php echo e($menu['icon']); ?>"></i>
									<p>
										<?php echo e($menu['label']); ?>
										<i class="right fas fa-angle-left"></i>
									</p>
								</a>
								<ul class="nav nav-treeview">
									<?php foreach ($children as $child): ?>
										<li class="nav-item">
											<a href="<?php echo site_url($child['url']); ?>" class="nav-link <?php echo is_active_menu($child['segment'], TRUE); ?>">
												<i class="nav-icon <?php echo e($child['icon']); ?>"></i>
												<p><?php echo e($child['label']); ?></p>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</li>
						<?php else: ?>
							<li class="nav-item">
								<a href="<?php echo site_url($menu['url']); ?>" class="nav-link <?php echo is_active_menu($menu['segment']); ?>">
									<i class="nav-icon <?php echo e($menu['icon']); ?>"></i>
									<p><?php echo e($menu['label']); ?></p>
								</a>
							</li>
						<?php endif; ?>
					<?php endif; ?>
				<?php endforeach; ?>
				<li class="nav-item">
					<?php echo form_open('logout', array('class' => 'nav-logout-form')); ?>
						<button type="submit" class="nav-link text-danger btn btn-link">
							<i class="nav-icon fas fa-sign-out-alt"></i>
							<p>Logout</p>
						</button>
					<?php echo form_close(); ?>
				</li>
			</ul>
		</nav>
	</div>
</aside>
