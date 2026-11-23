<?php
/**
 * Plugin Name: Onward Web Migration
 * Plugin URI: https://profiles.wordpress.org/chathuj/
 * Description: Migration tool for all your website data. Import or Export your website content with just a click.
 * Author: chathuJ
 * Author URI: https://chathuranga.org
 * Version: 1.0.7
 * Text Domain: onward-web-migration
 * Domain Path: /languages
 * Network: True
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if (!defined('ABSPATH')) {
	exit;
}

// Check SSL Mode
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
	$_SERVER['HTTPS'] = 'on';
}

// Plugin Basename
define('OWM_PLUGIN_BASENAME', function_exists('plugin_basename') ? plugin_basename(__FILE__) : basename(dirname(__FILE__)) . '/' . basename(__FILE__));

// Plugin Path
define('OWM_PATH', dirname(__FILE__));

// Plugin Url
define('OWM_URL', plugins_url('', OWM_PLUGIN_BASENAME));

// Plugin Storage Url
define('OWM_STORAGE_URL', plugins_url('storage', OWM_PLUGIN_BASENAME));

// Plugin Backups Url
define('OWM_BACKUPS_URL', content_url('owm-backups', OWM_PLUGIN_BASENAME));

// Themes Absolute Path
define('OWM_THEMES_PATH', get_theme_root());

// Include constants
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'constants.php';

// Include deprecated
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'deprecated.php';

// Include functions
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'functions.php';

// Include exceptions
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'exceptions.php';

// Include loader
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'loader.php';

// =========================================================================
// = All app initialization is done in Owm_Main_Controller __constructor =
// =========================================================================
if (!defined('WP_UNINSTALL_PLUGIN')) {
	$main_controller = new Owm_Main_Controller();
}
