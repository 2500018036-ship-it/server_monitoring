<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('remote_arg'))
{
	function remote_arg($value)
	{
		return "'".str_replace("'", "'\"'\"'", (string) $value)."'";
	}
}

if ( ! function_exists('remote_service_names'))
{
	function remote_service_names()
	{
		return array('nginx', 'apache2', 'httpd', 'php-fpm', 'php8.2-fpm', 'mysql', 'mariadb', 'docker', 'ssh', 'sshd', 'cron', 'crond');
	}
}
