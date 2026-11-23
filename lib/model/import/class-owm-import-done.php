<?php
/**
 * Import Done
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Done
{

	public static function execute($params)
	{

		// Check multisite.json file
		if (true === is_file(owm_multisite_path($params))) {
			// Read multisite.json file
			$handle = owm_open(owm_multisite_path($params), 'r');

			// Parse multisite.json file
			$multisite = owm_read($handle, filesize(owm_multisite_path($params)));
			$multisite = json_decode($multisite, true);

			// Close handle
			owm_close($handle);

			// Activate WordPress plugins confirmed by user
			$plugins = isset($multisite['Plugins']) && is_array($multisite['Plugins']) ? $multisite['Plugins'] : array();
			if (function_exists('current_user_can') && current_user_can('activate_plugins')) {
				owm_activate_plugins($plugins, true);
			}

		} else {

			// Check package.json file
			if (true === is_file(owm_package_path($params))) {

				// Read package.json file
				$handle = owm_open(owm_package_path($params), 'r');

				// Parse package.json file
				$package = owm_read($handle, filesize(owm_package_path($params)));
				$package = json_decode($package, true);

				// Close handle
				owm_close($handle);

				// Activate WordPress plugins confirmed by user
				$plugins = isset($package['Plugins']) && is_array($package['Plugins']) ? $package['Plugins'] : array();
				if (function_exists('current_user_can') && current_user_can('activate_plugins')) {
					owm_activate_plugins($plugins, true);
				}

				// Activate theme chosen by user (requires switch_themes capability)
				if (function_exists('current_user_can') && current_user_can('switch_themes')) {
					if (!empty($package['PreserveCurrentTheme'])) {
						// User chose to keep current site theme
						$current_tpl = !empty($package['CurrentTemplate']) ? $package['CurrentTemplate'] : get_option(OWM_ACTIVE_TEMPLATE);
						$current_sty = !empty($package['CurrentStylesheet']) ? $package['CurrentStylesheet'] : get_option(OWM_ACTIVE_STYLESHEET);
						if (!empty($current_tpl)) {
							owm_activate_template($current_tpl);
						}
						if (!empty($current_sty)) {
							owm_activate_stylesheet($current_sty);
							if (function_exists('switch_theme')) {
								switch_theme($current_sty);
							}
						}
					} else {
						// User chose to restore backup theme
						if (isset($package['Template']) && ($template = $package['Template'])) {
							owm_activate_template($template);
						}
						if (isset($package['Stylesheet']) && ($stylesheet = $package['Stylesheet'])) {
							owm_activate_stylesheet($stylesheet);
						}
						$active_stylesheet = isset($package['Stylesheet']) ? $package['Stylesheet'] : get_option(OWM_ACTIVE_STYLESHEET);
						if (!empty($active_stylesheet) && function_exists('switch_theme')) {
							switch_theme($active_stylesheet);
						}
					}
					if (function_exists('wp_clean_themes_cache')) {
						wp_clean_themes_cache();
					}
				}

				// Load old customizers / theme mods
				if (!empty($active_stylesheet)) {
					// 1. If ThemeMods was exported in package.json, restore it into wp_options
					if (isset($package['ThemeMods']) && is_array($package['ThemeMods']) && !empty($package['ThemeMods']) && function_exists('set_theme_mod')) {
						foreach ($package['ThemeMods'] as $mod_key => $mod_val) {
							set_theme_mod($mod_key, $mod_val);
						}
					}

					// 2. Clear options cache so fresh customizer settings are loaded
					if (function_exists('wp_cache_delete')) {
						wp_cache_delete('alloptions', 'options');
					}


					// 4. Force reload theme mods into memory
					if (function_exists('get_theme_mods')) {
						get_theme_mods();
					}
				}
			}
		}

		// Check blogs.json file
		if (true === is_file(owm_blogs_path($params))) {
			// Read blogs.json file
			$handle = owm_open(owm_blogs_path($params), 'r');

			// Parse blogs.json file
			$blogs = owm_read($handle, filesize(owm_blogs_path($params)));
			$blogs = json_decode($blogs, true);

			// Close handle
			owm_close($handle);

			// Loop over blogs
			foreach ($blogs as $blog) {

				// Activate WordPress plugins confirmed by user
				$plugins = isset($blog['New']['Plugins']) && is_array($blog['New']['Plugins']) ? $blog['New']['Plugins'] : array();
				if (function_exists('current_user_can') && current_user_can('activate_plugins')) {
					owm_activate_plugins($plugins, true);
				}

				// Activate WordPress template (requires switch_themes capability)
				if (isset($blog['New']['Template']) && ($template = $blog['New']['Template'])) {
					if (function_exists('current_user_can') && current_user_can('switch_themes')) {
						owm_activate_template($template);
					}
				}

				// Activate WordPress stylesheet (requires switch_themes capability)
				if (isset($blog['New']['Stylesheet']) && ($stylesheet = $blog['New']['Stylesheet'])) {
					if (function_exists('current_user_can') && current_user_can('switch_themes')) {
						owm_activate_stylesheet($stylesheet);
					}
				}

				if (function_exists('wp_clean_themes_cache')) {
					wp_clean_themes_cache();
				}
			}
		}

		// Ensure old plugins are deactivated even if neither package.json nor multisite.json existed
		if (!is_file(owm_multisite_path($params)) && !is_file(owm_package_path($params)) && !is_file(owm_blogs_path($params))) {
			owm_activate_plugins(array(), true);
		}

		// Invalidate object and theme caches
		owm_cache_flush();
		if (function_exists('wp_clean_themes_cache')) {
			wp_clean_themes_cache();
		}

		// Queue automatic permalink regeneration fallback
		update_option('owm_flush_permalinks', 2);

		// Set progress
		Owm_Status::done(
			__(
				'Your data has been imported successfully!',
				'onward-web-migration'
			),
			sprintf(
				/* translators: %s: permalinks settings URL */
				__(
					'Site contents and database imported successfully.<br /><strong>Permalinks have been refreshed automatically.</strong> If needed, you can review <a class="owm-no-underline" href="%s" target="_blank">Permalinks Settings</a>.',
					'onward-web-migration'
				),
				esc_url( admin_url( 'options-permalink.php#submit' ) )
			)
		);

		return $params;
	}
}
