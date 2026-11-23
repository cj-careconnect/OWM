<?php
/**
 * Uninstall Handler
 *
 * @package Onward_Web_Migration
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Plugin Path
if (!defined('OWM_PATH')) {
	define('OWM_PATH', dirname(__FILE__));
}

// Include constants if not already loaded
if (!defined('OWM_STATUS') && is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'constants.php')) {
	require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'constants.php';
}

// Delete options stored in the database
if (function_exists('delete_option')) {
	delete_option(defined('OWM_STATUS') ? OWM_STATUS : 'owm_status');
	delete_option(defined('OWM_SECRET_KEY') ? OWM_SECRET_KEY : 'owm_secret_key');
	delete_option(defined('OWM_AUTH_USER') ? OWM_AUTH_USER : 'owm_auth_user');
	delete_option(defined('OWM_AUTH_PASSWORD') ? OWM_AUTH_PASSWORD : 'owm_auth_password');
}
