<?php
/**
 * Confirm Import
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Confirm
{

	public static function execute($params)
	{
		$package = array();

		// Read package.json file if available
		if (is_file(owm_package_path($params))) {
			$handle = owm_open(owm_package_path($params), 'r');
			$package_content = owm_read($handle, filesize(owm_package_path($params)));
			$package = json_decode($package_content, true);
			owm_close($handle);
		}

		// If user already confirmed the plugin and theme selection from the UI modal, save choice and proceed
		if (!empty($params['owm_plugins_confirmed'])) {
			if (is_file(owm_package_path($params)) && is_array($package)) {
				$selected_plugins = array();
				if (isset($params['selected_plugins']) && is_array($params['selected_plugins'])) {
					$selected_plugins = array_values(array_map('sanitize_text_field', $params['selected_plugins']));
				}

				$package['Plugins'] = $selected_plugins;
				$package['UserSelectedPlugins'] = true;

				// Check if user selected to keep current theme instead of backup theme
				if (!empty($params['preserve_current_theme']) || (isset($params['selected_theme']) && $params['selected_theme'] === 'current')) {
					$package['PreserveCurrentTheme'] = true;
					$package['CurrentTemplate']      = get_template();
					$package['CurrentStylesheet']    = get_stylesheet();
				} else {
					unset($package['PreserveCurrentTheme']);
				}

				$handle = owm_open(owm_package_path($params), 'w');
				owm_write($handle, wp_json_encode($package));
				owm_close($handle);
			}

			Owm_Status::info(__('Preparing database and files...', 'onward-web-migration'));
			return $params;
		}

		// Non-interactive contexts (WP-CLI or scheduled) proceed automatically
		if ((defined('WP_CLI') && WP_CLI) || (function_exists('owm_is_scheduled_backup') && owm_is_scheduled_backup())) {
			Owm_Status::info(__('Preparing database and files...', 'onward-web-migration'));
			return $params;
		}

		$messages = array();

		// Set message
		$messages[] = __(
			'The import process will overwrite your website including the database, media, plugins, and themes. Please ensure that you have a backup of your data before proceeding.',
			'onward-web-migration'
		);

		// Check compatibility of PHP versions
		if (isset($package['PHP']['Version'])) {
			if (version_compare($package['PHP']['Version'], '7.0.0', '<') && version_compare(PHP_VERSION, '7.0.0', '>=')) {
				$messages[] = sprintf(
					'<i class="owm-import-info">%s <a href="#" target="_blank">%s</a></i>',
					esc_html__( 'Your backup is from a PHP 5 but the site that you are importing to is PHP 7. This could cause the import to fail.', 'onward-web-migration' ),
					esc_html__( 'Technical details', 'onward-web-migration' )
				);
			}
		}

		// Themes configuration (Backup Theme vs Current Site Theme)
		$current_theme_stylesheet = get_stylesheet();
		$current_theme_template   = get_template();
		$current_theme_obj        = function_exists('wp_get_theme') ? wp_get_theme($current_theme_stylesheet) : null;
		$current_theme_name       = ($current_theme_obj && $current_theme_obj->exists()) ? $current_theme_obj->get('Name') : ucwords(str_replace(array('-', '_'), ' ', $current_theme_stylesheet));

		$backup_theme_stylesheet = isset($package['Stylesheet']) ? $package['Stylesheet'] : (isset($package['Template']) ? $package['Template'] : '');
		$backup_theme_template   = isset($package['Template']) ? $package['Template'] : '';
		$backup_theme_obj        = (!empty($backup_theme_stylesheet) && function_exists('wp_get_theme')) ? wp_get_theme($backup_theme_stylesheet) : null;
		$backup_theme_name       = ($backup_theme_obj && $backup_theme_obj->exists()) ? $backup_theme_obj->get('Name') : ucwords(str_replace(array('-', '_'), ' ', $backup_theme_stylesheet));

		if (empty($backup_theme_name)) {
			$backup_theme_name = $current_theme_name ? $current_theme_name : __('Backup Theme', 'onward-web-migration');
		}

		$themes_data = array(
			'backup' => array(
				'name'       => $backup_theme_name,
				'stylesheet' => $backup_theme_stylesheet,
				'template'   => $backup_theme_template,
				'checked'    => true, // Backup theme active by default
			),
			'current' => array(
				'name'       => $current_theme_name ? $current_theme_name : __('Current Theme', 'onward-web-migration'),
				'stylesheet' => $current_theme_stylesheet,
				'template'   => $current_theme_template,
				'checked'    => false, // Current theme unselected by default
			),
		);

		// 1. Gather backup plugins from package.json
		$raw_backup_plugins = isset($package['Plugins']) && is_array($package['Plugins']) ? $package['Plugins'] : array();

		// 2. Gather installed plugins on current site
		if (!function_exists('get_plugins') && defined('ABSPATH') && is_file(ABSPATH . 'wp-admin/includes/plugin.php')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_installed = function_exists('get_plugins') ? get_plugins() : array();
		$installed_basenames = is_array($all_installed) ? array_keys($all_installed) : array();

		// Also check active plugins
		$active_plugins = function_exists('owm_active_plugins') ? owm_active_plugins() : get_option('active_plugins', array());
		if (is_array($active_plugins)) {
			foreach ($active_plugins as $ap) {
				if (!in_array($ap, $installed_basenames, true)) {
					$installed_basenames[] = $ap;
				}
			}
		}

		// Combined unique plugin basenames: backup plugins first, then installed plugins
		$all_plugin_basenames = $raw_backup_plugins;
		foreach ($installed_basenames as $inst_p) {
			if (!in_array($inst_p, $all_plugin_basenames, true)) {
				$all_plugin_basenames[] = $inst_p;
			}
		}

		$plugins_data = array();
		$has_security_plugins = false;

		foreach ($all_plugin_basenames as $plugin_basename) {
			$basename_lower = strtolower($plugin_basename);
			$is_security    = function_exists('owm_is_security_plugin') && owm_is_security_plugin($plugin_basename);
			$is_backup      = in_array($plugin_basename, $raw_backup_plugins, true);
			$is_installed   = in_array($plugin_basename, $installed_basenames, true);

			if ($is_security) {
				$has_security_plugins = true;
			}

			// User explicit rule:
			// "I have already installed these 3 plugins: Really Simple Security, Onward Web Migration, Plugin Check (PCP).
			// those 3 aslo should be in this list. But deselct except Really Simple Security"
			$is_checked = false;

			if (strpos($basename_lower, 'really-simple-ssl') !== false || strpos($basename_lower, 'really-simple-security') !== false) {
				$is_checked = true; // Really Simple Security stays selected
			} elseif (strpos($basename_lower, 'plugin-check') !== false) {
				$is_checked = false; // Plugin Check (PCP) is deselected
			} elseif (strpos($basename_lower, 'onward-web-migration') !== false || strpos($basename_lower, 'open-web-migration') !== false || strpos($basename_lower, 'open-wp-migration') !== false) {
				$is_checked = false; // Onward Web Migration is deselected
			} elseif ($is_backup) {
				// Other backup plugins: normal plugins checked by default, security deselected by default
				$is_checked = !$is_security;
			} elseif ($is_installed) {
				// Other installed plugins: deselected except security
				$is_checked = $is_security;
			}

			$title = function_exists('owm_format_plugin_title') ? owm_format_plugin_title($plugin_basename) : basename($plugin_basename);

			$plugins_data[] = array(
				'basename'     => $plugin_basename,
				'name'         => $title,
				'is_security'  => $is_security,
				'checked'      => $is_checked,
				'is_backup'    => $is_backup,
				'is_installed' => $is_installed,
			);
		}

		if ($has_security_plugins) {
			$messages[] = sprintf(
				'<div class="owm-security-notice" style="margin-top: 8px; font-size: 11px; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; padding: 6px 10px; border-radius: 6px;">%s</div>',
				esc_html__('Security plugins have been highlighted. Really Simple Security is kept selected to protect your site, while temporary tools like PCP and migration utilities are unselected by default.', 'onward-web-migration')
			);
		}

		// Set progress
		if (class_exists('Owm_Handler')) {
			Owm_Handler::mark_completed();
		}

		Owm_Status::confirm(
			wp_kses_post( implode( '', $messages ) ),
			array(
				'plugins'              => $plugins_data,
				'themes'               => $themes_data,
				'has_security_plugins' => $has_security_plugins,
			)
		);
		exit;
	}
}
