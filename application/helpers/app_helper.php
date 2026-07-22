<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('e'))
{
	function e($value)
	{
		return html_escape($value);
	}
}

if ( ! function_exists('user_session'))
{
	function user_session()
	{
		$CI =& get_instance();
		$user = $CI->session->userdata('user');

		return is_array($user) ? $user : array();
	}
}

if ( ! function_exists('current_role'))
{
	function current_role()
	{
		$user = user_session();

		return isset($user['role_name']) ? $user['role_name'] : '';
	}
}

if ( ! function_exists('has_role'))
{
	function has_role($roles)
	{
		if (empty($roles))
		{
			return TRUE;
		}

		return in_array(current_role(), $roles, TRUE);
	}
}

if ( ! function_exists('has_role_menu'))
{
	function has_role_menu($menu)
	{
		return isset($menu['roles']) ? has_role($menu['roles']) : TRUE;
	}
}

if ( ! function_exists('sidebar_menus'))
{
	function sidebar_menus()
	{
		$all = array('Super Admin', 'Admin', 'Operator', 'Viewer');
		$admin = array('Super Admin', 'Admin');
		$ops = array('Super Admin', 'Admin', 'Operator');

		return array(
			array('label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'dashboard', 'segment' => 'dashboard', 'roles' => $all),
			array('label' => 'Servers', 'icon' => 'fas fa-server', 'url' => 'servers', 'segment' => 'servers', 'roles' => $all),
			array('label' => 'Monitoring', 'icon' => 'fas fa-heartbeat', 'url' => 'monitoring', 'segment' => 'monitoring', 'roles' => $all),
			array('label' => 'Website', 'icon' => 'fas fa-globe', 'url' => 'website', 'segment' => 'website', 'roles' => $all),
			array('label' => 'Docker', 'icon' => 'fab fa-docker', 'url' => 'docker', 'segment' => 'docker', 'roles' => $ops),
			array(
				'label' => 'Database',
				'icon' => 'fas fa-database',
				'url' => 'database',
				'segment' => 'database',
				'roles' => $ops,
				'children' => array(
					array('label' => 'Dashboard DB', 'icon' => 'far fa-circle', 'url' => 'database', 'segment' => 'database', 'roles' => $ops),
					array('label' => 'DB Explorer', 'icon' => 'fas fa-search', 'url' => 'database/explorer', 'segment' => 'database/explorer', 'roles' => $ops),
					array('label' => 'SQL Query', 'icon' => 'fas fa-code', 'url' => 'database/query', 'segment' => 'database/query', 'roles' => $ops),
					array('label' => 'Backup History', 'icon' => 'fas fa-history', 'url' => 'database/history', 'segment' => 'database/history', 'roles' => $ops),
					array('label' => 'Backup Config', 'icon' => 'fas fa-cog', 'url' => 'database/backup-config', 'segment' => 'database/backup-config', 'roles' => $ops),
				),
			),
			array('label' => 'Logs', 'icon' => 'fas fa-clipboard-list', 'url' => 'logs', 'segment' => 'logs', 'roles' => $admin),
			array('label' => 'AI Assistant', 'icon' => 'fas fa-robot', 'url' => 'ai', 'segment' => 'ai', 'roles' => $ops),
			array('label' => 'SSH Config', 'icon' => 'fas fa-key', 'url' => 'ssh-config', 'segment' => 'ssh-config', 'roles' => $admin),
			array('label' => 'Terminal', 'icon' => 'fas fa-terminal', 'url' => 'terminal', 'segment' => 'terminal', 'roles' => $admin),
			array('label' => 'Quick Actions', 'icon' => 'fas fa-bolt', 'url' => 'quick-actions', 'segment' => 'quick-actions', 'roles' => $ops),
			array('label' => 'Service Manager', 'icon' => 'fas fa-cogs', 'url' => 'service-manager', 'segment' => 'service-manager', 'roles' => $ops),
			array('label' => 'File Manager', 'icon' => 'fas fa-folder-open', 'url' => 'file-manager', 'segment' => 'file-manager', 'roles' => $admin),
			array('label' => 'Backup Manager', 'icon' => 'fas fa-archive', 'url' => 'backup-manager', 'segment' => 'backup-manager', 'roles' => $admin),
			array('label' => 'Cron Manager', 'icon' => 'fas fa-clock', 'url' => 'cron-manager', 'segment' => 'cron-manager', 'roles' => $admin),
			array('label' => 'Firewall Manager', 'icon' => 'fas fa-shield-alt', 'url' => 'firewall-manager', 'segment' => 'firewall-manager', 'roles' => array('Super Admin')),
			array('label' => 'SSL Manager', 'icon' => 'fas fa-certificate', 'url' => 'ssl-manager', 'segment' => 'ssl-manager', 'roles' => $admin),
			array('label' => 'System Manager', 'icon' => 'fas fa-tools', 'url' => 'system-manager', 'segment' => 'system-manager', 'roles' => $admin),
			array('label' => 'Users', 'icon' => 'fas fa-users', 'url' => 'users', 'segment' => 'users', 'roles' => $admin),
			array(
				'label' => 'Settings',
				'icon' => 'fas fa-cogs',
				'url' => 'settings',
				'segment' => 'settings',
				'roles' => $admin,
				'children' => array(
					array('label' => 'General', 'icon' => 'far fa-circle', 'url' => 'settings', 'segment' => 'settings', 'roles' => $admin),
					array('label' => 'Telegram Bot', 'icon' => 'fab fa-telegram-plane', 'url' => 'settings/telegram-bot', 'segment' => 'settings/telegram-bot', 'roles' => $admin),
					array('label' => 'Notification Settings', 'icon' => 'fas fa-bell', 'url' => 'settings/notification-settings', 'segment' => 'settings/notification-settings', 'roles' => $admin),
				),
			),
			array('label' => 'Profile', 'icon' => 'fas fa-user-circle', 'url' => 'profile', 'segment' => 'profile', 'roles' => $all),
		);
	}
}

if ( ! function_exists('is_active_menu'))
{
	function is_active_menu($segment, $exact = FALSE)
	{
		$CI =& get_instance();
		$segment = trim(strtolower((string) $segment), '/');

		if ($exact || strpos($segment, '/') !== FALSE)
		{
			$current = trim(strtolower((string) uri_string()), '/');

			return $current === $segment ? 'active' : '';
		}

		$current = strtolower((string) $CI->uri->segment(1));

		return $current === $segment ? 'active' : '';
	}
}

if ( ! function_exists('is_open_menu'))
{
	function is_open_menu($menu)
	{
		if (empty($menu['children']))
		{
			return is_active_menu($menu['segment']) === 'active';
		}

		foreach ($menu['children'] as $child)
		{
			if (has_role($child['roles']) && is_active_menu($child['segment'], TRUE) === 'active')
			{
				return TRUE;
			}
		}

		return is_active_menu($menu['segment']) === 'active';
	}
}

if ( ! function_exists('avatar_url'))
{
	function avatar_url($photo)
	{
		if ($photo && file_exists(FCPATH.'assets/uploads/users/'.$photo))
		{
			return base_url('assets/uploads/users/'.$photo);
		}

		return base_url('assets/img/default-avatar.svg');
	}
}

if ( ! function_exists('uploaded_asset_url'))
{
	function uploaded_asset_url($path, $fallback)
	{
		if ($path && file_exists(FCPATH.$path))
		{
			return base_url($path);
		}

		return base_url($fallback);
	}
}

if ( ! function_exists('role_badge_class'))
{
	function role_badge_class($role)
	{
		$classes = array(
			'Super Admin' => 'badge-danger',
			'Admin' => 'badge-primary',
			'Operator' => 'badge-warning',
			'Viewer' => 'badge-secondary',
		);

		return isset($classes[$role]) ? $classes[$role] : 'badge-light';
	}
}
