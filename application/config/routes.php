<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'login';
$route['logout'] = 'login/logout';
$route['forgot-password'] = 'login/forgot_password';
$route['reset-password'] = 'login/reset_password';
$route['profile/change-password'] = 'profile/change_password';
$route['terminal'] = 'terminal';
$route['ssh-config'] = 'ssh_config';
$route['quick-actions'] = 'quick_actions';
$route['service-manager'] = 'service_manager';
$route['file-manager'] = 'file_manager';
$route['backup-manager'] = 'backup_manager';
$route['database/history'] = 'database/history';
$route['database/explorer'] = 'database/explorer';
$route['database/tables'] = 'database/tables';
$route['database/table-detail'] = 'database/table_detail';
$route['database/data'] = 'database/data';
$route['database/query'] = 'database/query';
$route['database/backup-config'] = 'database/backup_config';
$route['database/update-backup-config'] = 'database/update_backup_config';
$route['database/delete-backup/(:num)'] = 'database/delete_backup/$1';
$route['cron-manager'] = 'cron_manager';
$route['firewall-manager'] = 'firewall_manager';
$route['ssl-manager'] = 'ssl_manager';
$route['system-manager'] = 'system_manager';
$route['api/server'] = 'api/server';
$route['api/pull-ssh-metrics'] = 'api/pull_ssh_metrics';
$route['api/cpu'] = 'api/cpu';
$route['api/ram'] = 'api/ram';
$route['api/storage'] = 'api/storage';
$route['api/network'] = 'api/network';
$route['api/process'] = 'api/process';
$route['api/service'] = 'api/service';
$route['api/docker'] = 'api/docker';
$route['api/database'] = 'api/database';
$route['api/logs'] = 'api/logs';
$route['api/system'] = 'api/system';
$route['api/heartbeat'] = 'api/heartbeat';
$route['api/metrics'] = 'api/metrics';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
