<?php
/**
 * Core Functions
 *
 * @package Onward_Web_Migration
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Safe wrapper for wp_is_writable which falls back gracefully if wp-admin/includes/file.php is not yet loaded.
 *
 * @param  string $path Path to check.
 * @return bool
 */
function owm_is_writable($path)
{
	if (function_exists('wp_is_writable')) {
		return wp_is_writable($path);
	}
	if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/file.php')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if (function_exists('wp_is_writable')) {
			return wp_is_writable($path);
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Fallback when wp-admin/includes/file.php is not yet loaded.
	return is_writable($path);
}

/**
 * Get storage absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
/**
 * Ensure directory contains security protection files (.htaccess, web.config, index.php)
 * with strict deny rules to prevent direct HTTP access and script execution.
 *
 * @param  string $dir Directory path to protect
 * @return void
 */
function owm_ensure_directory_protection($dir)
{
	if (empty($dir) || !is_dir($dir)) {
		return;
	}

	// 1. index.php
	$index_file = $dir . DIRECTORY_SEPARATOR . 'index.php';
	if (!file_exists($index_file) && function_exists('owm_is_writable') && owm_is_writable($dir)) {
		@file_put_contents($index_file, "<?php\n// Silence is golden.\nexit;\n");
	}

	// 2. .htaccess
	$htaccess_file = $dir . DIRECTORY_SEPARATOR . '.htaccess';
	if (!file_exists($htaccess_file) && function_exists('owm_is_writable') && owm_is_writable($dir)) {
		$rules = implode(PHP_EOL, array(
			'# Deny direct access to all files in this directory',
			'<IfModule mod_mime.c>',
			'AddType application/zip .zip',
			'</IfModule>',
			'<IfModule mod_dir.c>',
			'DirectoryIndex index.php',
			'</IfModule>',
			'<IfModule mod_autoindex.c>',
			'Options -Indexes',
			'</IfModule>',
			'<IfModule !mod_authz_core.c>',
			'Order deny,allow',
			'Deny from all',
			'</IfModule>',
			'<IfModule mod_authz_core.c>',
			'Require all denied',
			'</IfModule>',
		));
		@file_put_contents($htaccess_file, $rules);
	}

	// 3. web.config
	$webconfig_file = $dir . DIRECTORY_SEPARATOR . 'web.config';
	if (!file_exists($webconfig_file) && function_exists('owm_is_writable') && owm_is_writable($dir)) {
		$xml = implode(PHP_EOL, array(
			'<configuration>',
			'  <system.webServer>',
			'    <authorization>',
			'      <deny users="*" />',
			'    </authorization>',
			'  </system.webServer>',
			'</configuration>',
		));
		@file_put_contents($webconfig_file, $xml);
	}
}

function owm_storage_path($params)
{
	$storage_id = '';
	if (is_array($params) && !empty($params['storage'])) {
		$storage_id = (string) $params['storage'];
	} elseif (is_string($params) && !empty($params)) {
		$storage_id = $params;
	}

	if (empty($storage_id)) {
		throw new Owm_Storage_Exception(
			wp_kses_post(
				__('Unable to locate storage path. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration')
			)
		);
	}

	// In compliance with WordPress guidelines, temporary files are written to wp_upload_dir() with fallback
	$upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : null;
	if (!empty($upload_dir['basedir']) && owm_is_writable($upload_dir['basedir'])) {
		$base_storage = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'owm-storage';
	} elseif (defined('OWM_BACKUPS_PATH') && is_dir(OWM_BACKUPS_PATH)) {
		$base_storage = OWM_BACKUPS_PATH . DIRECTORY_SEPARATOR . '.storage';
	} else {
		$base_storage = OWM_STORAGE_PATH;
	}

	if (!is_dir($base_storage)) {
		wp_mkdir_p($base_storage);
	}

	owm_ensure_directory_protection($base_storage);

	// Get storage path for session/chunk
	$storage = $base_storage . DIRECTORY_SEPARATOR . basename($storage_id);
	if (!is_dir($storage)) {
		wp_mkdir_p($storage);
	}

	owm_ensure_directory_protection($storage);

	return $storage;
}

/**
 * Get backup absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_backup_path($params)
{
	if (empty($params['archive'])) {
		throw new Owm_Archive_Exception(
			wp_kses_post(
				__('Unable to locate archive path. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration')
			)
		);
	}

	$archive = sanitize_file_name($params['archive']);

	// Validate archive path
	if (validate_file($archive) !== 0) {
		throw new Owm_Archive_Exception(
			wp_kses_post(
				__('Invalid archive path. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration')
			)
		);
	}

	$ext = strtolower(pathinfo($archive, PATHINFO_EXTENSION));
	if (!in_array($ext, array('zip', 'wpress'), true) || preg_match('/\.php/i', $archive)) {
		throw new Owm_Archive_Exception(
			wp_kses_post(
				__('Invalid archive file extension. Only .zip archives are allowed.', 'onward-web-migration')
			)
		);
	}

	if (!is_dir(OWM_BACKUPS_PATH)) {
		wp_mkdir_p(OWM_BACKUPS_PATH);
	}

	owm_ensure_directory_protection(OWM_BACKUPS_PATH);

	return OWM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $archive;
}

/**
 * Get archive absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_archive_path($params)
{
	if (empty($params['archive'])) {
		throw new Owm_Archive_Exception(
			wp_kses_post(
				__('Unable to locate archive path. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration')
			)
		);
	}

	$archive = sanitize_file_name($params['archive']);

	// Validate archive path
	if (validate_file($archive) !== 0) {
		throw new Owm_Archive_Exception(
			wp_kses_post(
				__('Invalid archive path. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration')
			)
		);
	}

	$ext = strtolower(pathinfo($archive, PATHINFO_EXTENSION));
	if (!in_array($ext, array('zip', 'wpress'), true) || preg_match('/\.php/i', $archive)) {
		throw new Owm_Archive_Exception(
			wp_kses_post(
				__('Invalid archive file extension. Only .zip archives are allowed.', 'onward-web-migration')
			)
		);
	}

	// Get archive path
	if (empty($params['owm_manual_restore'])) {
		$storage = owm_storage_path($params);
		owm_ensure_directory_protection($storage);
		return $storage . DIRECTORY_SEPARATOR . $archive;
	}

	return owm_backup_path($params);
}

/**
 * Get export log absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_export_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_EXPORT_NAME;
}

/**
 * Get import log absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_import_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_IMPORT_NAME;
}

/**
 * Get multipart.list absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_multipart_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_MULTIPART_NAME;
}

/**
 * Get filemap.list absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_filemap_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_FILEMAP_NAME;
}

/**
 * Get package.json absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_package_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_PACKAGE_NAME;
}

/**
 * Get multisite.json absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_multisite_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_MULTISITE_NAME;
}

/**
 * Get blogs.json absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_blogs_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_BLOGS_NAME;
}

/**
 * Get settings.json absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_settings_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_SETTINGS_NAME;
}

/**
 * Get database.sql absolute path
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_database_path($params)
{
	return owm_storage_path($params) . DIRECTORY_SEPARATOR . OWM_DATABASE_NAME;
}

/**
 * Get error log absolute path
 *
 * @return string
 */
function owm_error_path()
{
	return OWM_STORAGE_PATH . DIRECTORY_SEPARATOR . OWM_ERROR_NAME;
}

/**
 * Get WordPress content directory
 *
 * @param  string $path Relative path
 * @return string
 */
function owm_content_path($path = null)
{
	if (empty($path)) {
		return WP_CONTENT_DIR;
	}

	return WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $path;
}

/**
 * Get archive name
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_archive_name($params)
{
	return basename($params['archive']);
}

/**
 * Get backup URL address
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_backup_url($params)
{
	$archive = isset($params['archive']) ? basename($params['archive']) : '';
	if (function_exists('admin_url') && function_exists('wp_create_nonce')) {
		return admin_url(
			sprintf(
				'admin-post.php?action=owm_download_backup&archive=%s&owm_nonce=%s',
				rawurlencode($archive),
				wp_create_nonce('owm_download_backup')
			)
		);
	}
	return OWM_BACKUPS_URL . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $archive);
}

/**
 * Get archive size in bytes
 *
 * @param  array   $params Request parameters
 * @return integer
 */
function owm_archive_bytes($params)
{
	return filesize(owm_archive_path($params));
}

/**
 * Get backup size in bytes
 *
 * @param  array   $params Request parameters
 * @return integer
 */
function owm_backup_bytes($params)
{
	return filesize(owm_backup_path($params));
}

/**
 * Get database size in bytes
 *
 * @param  array   $params Request parameters
 * @return integer
 */
function owm_database_bytes($params)
{
	return filesize(owm_database_path($params));
}

/**
 * Get package size in bytes
 *
 * @param  array   $params Request parameters
 * @return integer
 */
function owm_package_bytes($params)
{
	return filesize(owm_package_path($params));
}

/**
 * Get multisite size in bytes
 *
 * @param  array   $params Request parameters
 * @return integer
 */
function owm_multisite_bytes($params)
{
	return filesize(owm_multisite_path($params));
}

/**
 * Get archive size as text
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_archive_size($params)
{
	return size_format(filesize(owm_archive_path($params)));
}

/**
 * Get backup size as text
 *
 * @param  array  $params Request parameters
 * @return string
 */
function owm_backup_size($params)
{
	return size_format(filesize(owm_backup_path($params)));
}

/**
 * Parse file size
 *
 * @param  string $size    File size
 * @param  string $default Default size
 * @return string
 */
function owm_parse_size($size, $default = null)
{
	$suffixes = array(
		'' => 1,
		'k' => 1000,
		'm' => 1000000,
		'g' => 1000000000,
	);

	// Parse size format
	if (preg_match('/([0-9]+)\s*(k|m|g)?(b?(ytes?)?)/i', $size, $match)) {
		return $match[1] * $suffixes[strtolower($match[2])];
	}

	return $default;
}

/**
 * Get current site name
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_site_name($blog_id = null)
{
	return wp_parse_url(get_site_url($blog_id), PHP_URL_HOST);
}

/**
 * Get archive file name
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_archive_file($blog_id = null)
{
	$name = array();

	// Add domain
	$name[] = wp_parse_url(get_site_url($blog_id), PHP_URL_HOST);

	// Add path
	if (($path = explode('/', wp_parse_url(get_site_url($blog_id), PHP_URL_PATH)))) {
		foreach ($path as $directory) {
			if ($directory) {
				$name[] = $directory;
			}
		}
	}

	// Add year, month and day
	$name[] = gmdate('Ymd');

	// Add hours, minutes and seconds
	$name[] = gmdate('His');

	// Add unique identifier
	$name[] = strtolower(function_exists('wp_generate_password') ? wp_generate_password(32, false, false) : bin2hex(random_bytes(16)));

	return sprintf('%s.zip', strtolower(implode('-', $name)));
}

/**
 * Get archive folder name
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_archive_folder($blog_id = null)
{
	$name = array();

	// Add domain
	$name[] = wp_parse_url(get_site_url($blog_id), PHP_URL_HOST);

	// Add path
	if (($path = explode('/', wp_parse_url(get_site_url($blog_id), PHP_URL_PATH)))) {
		foreach ($path as $directory) {
			if ($directory) {
				$name[] = $directory;
			}
		}
	}

	return strtolower(implode('-', $name));
}

/**
 * Get archive bucket name
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_archive_bucket($blog_id = null)
{
	$name = array();

	// Add domain
	if (($domain = explode('.', wp_parse_url(get_site_url($blog_id), PHP_URL_HOST)))) {
		foreach ($domain as $subdomain) {
			if ($subdomain) {
				$name[] = $subdomain;
			}
		}
	}

	// Add path
	if (($path = explode('/', wp_parse_url(get_site_url($blog_id), PHP_URL_PATH)))) {
		foreach ($path as $directory) {
			if ($directory) {
				$name[] = $directory;
			}
		}
	}

	return strtolower(implode('-', $name));
}

/**
 * Get archive vault name
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_archive_vault($blog_id = null)
{
	$name = array();

	// Add domain
	if (($domain = explode('.', wp_parse_url(get_site_url($blog_id), PHP_URL_HOST)))) {
		foreach ($domain as $subdomain) {
			if ($subdomain) {
				$name[] = $subdomain;
			}
		}
	}

	// Add path
	if (($path = explode('/', wp_parse_url(get_site_url($blog_id), PHP_URL_PATH)))) {
		foreach ($path as $directory) {
			if ($directory) {
				$name[] = $directory;
			}
		}
	}

	return strtolower(implode('-', $name));
}

/**
 * Get archive project name
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_archive_project($blog_id = null)
{
	$name = array();

	// Add domain
	if (($domain = explode('.', wp_parse_url(get_site_url($blog_id), PHP_URL_HOST)))) {
		foreach ($domain as $subdomain) {
			if ($subdomain) {
				$name[] = $subdomain;
			}
		}
	}

	// Add path
	if (($path = explode('/', wp_parse_url(get_site_url($blog_id), PHP_URL_PATH)))) {
		foreach ($path as $directory) {
			if ($directory) {
				$name[] = $directory;
			}
		}
	}

	return strtolower(implode('-', $name));
}

/**
 * Get archive share name
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_archive_share($blog_id = null)
{
	$name = array();

	// Add domain
	if (($domain = explode('.', wp_parse_url(get_site_url($blog_id), PHP_URL_HOST)))) {
		foreach ($domain as $subdomain) {
			if ($subdomain) {
				$name[] = $subdomain;
			}
		}
	}

	// Add path
	if (($path = explode('/', wp_parse_url(get_site_url($blog_id), PHP_URL_PATH)))) {
		foreach ($path as $directory) {
			if ($directory) {
				$name[] = $directory;
			}
		}
	}

	return strtolower(implode('-', $name));
}

/**
 * Get storage folder name
 *
 * @return string
 */
function owm_storage_folder()
{
	return uniqid();
}

/**
 * Check whether blog ID is main site
 *
 * @param  integer $blog_id Blog ID
 * @return boolean
 */
function owm_main_site($blog_id = null)
{
	return $blog_id === null || $blog_id === 0 || $blog_id === 1;
}

/**
 * Get sites absolute path by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_sites_path($blog_id = null)
{
	if (owm_main_site($blog_id)) {
		return OWM_UPLOADS_PATH;
	}

	return OWM_SITES_PATH . DIRECTORY_SEPARATOR . $blog_id;
}

/**
 * Get files absolute path by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_files_path($blog_id = null)
{
	if (owm_main_site($blog_id)) {
		return OWM_UPLOADS_PATH;
	}

	return OWM_BLOGSDIR_PATH . DIRECTORY_SEPARATOR . $blog_id . DIRECTORY_SEPARATOR . 'files';
}

/**
 * Get blogs.dir absolute path by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_blogsdir_path($blog_id = null)
{
	if (owm_main_site($blog_id)) {
		return '/wp-content/uploads/';
	}

	return "/wp-content/blogs.dir/{$blog_id}/files/";
}

/**
 * Get blogs.dir URL by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_blogsdir_url($blog_id = null)
{
	if (owm_main_site($blog_id)) {
		return get_site_url($blog_id, '/wp-content/uploads/');
	}

	return get_site_url($blog_id, "/wp-content/blogs.dir/{$blog_id}/files/");
}

/**
 * Get uploads absolute path by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_uploads_path($blog_id = null)
{
	if (owm_main_site($blog_id)) {
		return '/wp-content/uploads/';
	}

	return "/wp-content/uploads/sites/{$blog_id}/";
}

/**
 * Get uploads URL by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_uploads_url($blog_id = null)
{
	if (owm_main_site($blog_id)) {
		return get_site_url($blog_id, '/wp-content/uploads/');
	}

	return get_site_url($blog_id, "/wp-content/uploads/sites/{$blog_id}/");
}

/**
 * Get OWM table prefix by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_prefix($blog_id = null)
{
	// Set base table prefix
	if (owm_main_site($blog_id)) {
		return OWM_TABLE_PREFIX;
	}

	return OWM_TABLE_PREFIX . $blog_id . '_';
}

/**
 * Backward compatibility for legacy extensions
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_owm_prefix($blog_id = null)
{
	return owm_prefix($blog_id);
}

if (!function_exists('owm_onward_prefix')) {
	function owm_onward_prefix($blog_id = null)
	{
		return owm_prefix($blog_id);
	}
}

if (!function_exists('owm_omini_prefix')) {
	function owm_omini_prefix($blog_id = null)
	{
		return owm_prefix($blog_id);
	}
}

/**
 * Get WordPress table prefix by blog ID
 *
 * @param  integer $blog_id Blog ID
 * @return string
 */
function owm_table_prefix($blog_id = null)
{
	global $wpdb;

	// Set base table prefix
	if (owm_main_site($blog_id)) {
		return $wpdb->base_prefix;
	}

	return $wpdb->base_prefix . $blog_id . '_';
}

/**
 * Get default content filters
 *
 * @param  array $filters List of files and directories
 * @return array
 */
function owm_content_filters($filters = array())
{
	return array_merge($filters, array(
		OWM_BACKUPS_NAME,
		OWM_PACKAGE_NAME,
		OWM_MULTISITE_NAME,
		OWM_DATABASE_NAME,
	));
}

/**
 * Get default plugin filters
 *
 * @param  array $filters List of plugins
 * @return array
 */
function owm_plugin_filters($filters = array())
{
	// WP Migration Plugin
	if (defined('OWM_PLUGIN_BASENAME')) {
		$filters[] = 'plugins' . DIRECTORY_SEPARATOR . dirname(OWM_PLUGIN_BASENAME);
	} else {
		$filters[] = 'plugins' . DIRECTORY_SEPARATOR . 'onward-web-migration';
	}

	return $filters;
}

/**
 * Get active OWM plugins
 *
 * @param  array $plugins List of plugins
 * @return array
 */
function owm_active_owm_plugins($plugins = array())
{
	// WP Migration Plugin
	if (defined('OWM_PLUGIN_BASENAME')) {
		$plugins[] = OWM_PLUGIN_BASENAME;
	}

	return $plugins;
}

/**
 * Backward compatibility for legacy  extensions
 *
 * @param  array $plugins
 * @return array
 */
if (!function_exists('owm_active_onward_plugins')) {
	function owm_active_onward_plugins($plugins = array())
	{
		return owm_active_owm_plugins($plugins);
	}
}

if (!function_exists('owm_active_omini_plugins')) {
	function owm_active_omini_plugins($plugins = array())
	{
		return owm_active_owm_plugins($plugins);
	}
}

/**
 * Get active sitewide plugins
 *
 * @return array
 */
function owm_active_sitewide_plugins()
{
	return array_keys(get_site_option(OWM_ACTIVE_SITEWIDE_PLUGINS, array()));
}

/**
 * Get active plugins
 *
 * @return array
 */
function owm_active_plugins()
{
	return array_values(get_option(OWM_ACTIVE_PLUGINS, array()));
}

/**
 * Set active sitewide plugins (inspired by WordPress activate_plugins() function)
 *
 * @param  array   $plugins List of plugins
 * @return boolean
 */
function owm_activate_sitewide_plugins($plugins)
{
	$current = get_site_option(OWM_ACTIVE_SITEWIDE_PLUGINS, array());

	// Add plugins
	foreach ($plugins as $plugin) {
		if (!isset($current[$plugin]) && !is_wp_error(validate_plugin($plugin))) {
			$current[$plugin] = time();
		}
	}

	return update_site_option(OWM_ACTIVE_SITEWIDE_PLUGINS, $current);
}

/**
 * Set active plugins (inspired by WordPress activate_plugins() function)
 *
 * @param  array   $plugins           List of plugins
 * @param  boolean $deactivate_others Whether to deactivate old plugins that are not in the new list
 * @return boolean
 */
function owm_activate_plugins($plugins, $deactivate_others = false)
{
	if (!function_exists('validate_plugin') && defined('ABSPATH') && is_file(ABSPATH . 'wp-admin/includes/plugin.php')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$current = get_option(OWM_ACTIVE_PLUGINS, array());
	if (!is_array($current)) {
		$current = array();
	}

	$owm_plugins = owm_active_owm_plugins();

	if ($deactivate_others) {
		$new_list = array();

		// Always keep Onward Web Migration / migration plugins active
		foreach ($owm_plugins as $owm_p) {
			if (!in_array($owm_p, $new_list, true)) {
				$new_list[] = $owm_p;
			}
		}

		// Add imported plugins if they exist on disk and pass validation (skip duplicate migration plugin copies in different folder names)
		if (is_array($plugins)) {
			foreach ($plugins as $plugin) {
				$base_file = basename($plugin);
				if (in_array($base_file, array('onward-web-migration.php', 'omini-web-migration.php', 'open-web-migration.php', 'open-wp-migration.php'), true) && !in_array($plugin, $owm_plugins, true)) {
					continue;
				}
				if (!in_array($plugin, $new_list, true)) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
					$is_valid = !function_exists('validate_plugin') || !is_wp_error(validate_plugin($plugin));
					if (is_file($plugin_file) && $is_valid) {
						$new_list[] = $plugin;
					}
				}
			}
		}

		sort($new_list);
		return update_option(OWM_ACTIVE_PLUGINS, $new_list);
	}

	// Normal add plugins
	if (is_array($plugins)) {
		foreach ($plugins as $plugin) {
			$is_valid = !function_exists('validate_plugin') || !is_wp_error(validate_plugin($plugin));
			if (!in_array($plugin, $current, true) && $is_valid) {
				$current[] = $plugin;
			}
		}
	}

	sort($current);

	return update_option(OWM_ACTIVE_PLUGINS, $current);
}

/**
 * Get active template
 *
 * @return string
 */
function owm_active_template()
{
	return get_option(OWM_ACTIVE_TEMPLATE);
}

/**
 * Get active stylesheet
 *
 * @return string
 */
function owm_active_stylesheet()
{
	return get_option(OWM_ACTIVE_STYLESHEET);
}

/**
 * Set active template
 *
 * @param  string  $template Template name
 * @return boolean
 */
function owm_activate_template($template)
{
	return update_option(OWM_ACTIVE_TEMPLATE, $template);
}

/**
 * Set active stylesheet
 *
 * @param  string  $stylesheet Stylesheet name
 * @return boolean
 */
function owm_activate_stylesheet($stylesheet)
{
	return update_option(OWM_ACTIVE_STYLESHEET, $stylesheet);
}

/**
 * Set inactive sitewide plugins (inspired by WordPress deactivate_plugins() function)
 *
 * @param  array   $plugins List of plugins
 * @return boolean
 */
function owm_deactivate_sitewide_plugins($plugins)
{
	$current = get_site_option(OWM_ACTIVE_SITEWIDE_PLUGINS, array());

	// Add plugins
	foreach ($plugins as $plugin) {
		if (isset($current[$plugin])) {
			unset($current[$plugin]);
		}
	}

	return update_site_option(OWM_ACTIVE_SITEWIDE_PLUGINS, $current);
}


/**
 * Set inactive plugins (inspired by WordPress deactivate_plugins() function)
 *
 * @param  array   $plugins List of plugins
 * @return boolean
 */
function owm_deactivate_plugins($plugins)
{
	$current = get_option(OWM_ACTIVE_PLUGINS, array());

	// Remove plugins
	foreach ($plugins as $plugin) {
		if (($key = array_search($plugin, $current)) !== false) {
			unset($current[$key]);
		}
	}

	sort($current);

	return update_option(OWM_ACTIVE_PLUGINS, $current);
}

/**
 * Deactivate Jetpack modules
 *
 * @param  array   $modules List of modules
 * @return boolean
 */
function owm_deactivate_jetpack_modules($modules)
{
	$current = get_option(OWM_JETPACK_ACTIVE_MODULES, array());

	// Remove modules
	foreach ($modules as $module) {
		if (($key = array_search($module, $current)) !== false) {
			unset($current[$key]);
		}
	}

	sort($current);

	return update_option(OWM_JETPACK_ACTIVE_MODULES, $current);
}

/**
 * Discover plugin basename
 *
 * @param  string $basename Plugin basename
 * @return string
 */
function owm_discover_plugin_basename($basename)
{
	if (($plugins = get_plugins())) {
		foreach ($plugins as $plugin => $info) {
			if (strpos(dirname($plugin), dirname($basename)) !== false) {
				if (basename($plugin) === basename($basename)) {
					return $plugin;
				}
			}
		}
	}

	return $basename;
}

/**
 * Return list of known security and login redirection plugin slugs
 *
 * @return array
 */
function owm_get_security_plugin_slugs()
{
	return array(
		'really-simple-ssl',
		'really-simple-security',
		'wordpress-https',
		'wp-force-ssl',
		'invisible-recaptcha',
		'wps-hide-login',
		'hide-my-wp',
		'hide-my-wordpress',
		'mycustomwidget',
		'lockdown-wp-admin',
		'rename-wp-login',
		'wordfence',
		'better-wp-security',
		'all-in-one-wp-security-and-firewall',
		'two-factor',
		'wp-2fa',
		'limit-login-attempts',
		'limit-login-attempts-reloaded',
		'loginizer',
		'sucuri-scanner',
		'secupress',
		'defender-security',
		'shield-security',
		'wp-cerber',
		'login-lockdown',
	);
}

/**
 * Check whether a given plugin basename belongs to a security or login redirection plugin
 *
 * @param  string $plugin_basename
 * @return boolean
 */
function owm_is_security_plugin($plugin_basename)
{
	if (empty($plugin_basename)) {
		return false;
	}

	$basename_lower = strtolower($plugin_basename);
	$slugs = owm_get_security_plugin_slugs();

	foreach ($slugs as $slug) {
		if (strpos($basename_lower, $slug) !== false) {
			return true;
		}
	}

	// Pattern match common security/login redirection indicators
	if (preg_match('/(hide[-_]login|rename[-_]login|two[-_]factor|2fa|recaptcha|firewall|force[-_]ssl|security)/i', $basename_lower)) {
		return true;
	}

	return false;
}

/**
 * Format human-readable title for a plugin basename
 *
 * @param  string $plugin_basename
 * @return string
 */
function owm_format_plugin_title($plugin_basename)
{
	if (empty($plugin_basename)) {
		return '';
	}

	$basename_lower = strtolower($plugin_basename);

	// Explicit friendly titles for known plugins
	if (strpos($basename_lower, 'really-simple-ssl') !== false || strpos($basename_lower, 'really-simple-security') !== false) {
		return 'Really Simple Security';
	}
	if (strpos($basename_lower, 'plugin-check') !== false) {
		return 'Plugin Check (PCP)';
	}
	if (strpos($basename_lower, 'onward-web-migration') !== false || strpos($basename_lower, 'omini-web-migration') !== false || strpos($basename_lower, 'open-web-migration') !== false || strpos($basename_lower, 'open-wp-migration') !== false) {
		return 'Onward Web Migration';
	}

	if (defined('WP_PLUGIN_DIR') && is_file(WP_PLUGIN_DIR . '/' . $plugin_basename)) {
		if (!function_exists('get_plugin_data') && defined('ABSPATH') && is_file(ABSPATH . 'wp-admin/includes/plugin.php')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if (function_exists('get_plugin_data')) {
			$data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_basename, false, false);
			if (!empty($data['Name'])) {
				return $data['Name'];
			}
		}
	}

	$folder = dirname($plugin_basename);
	if (!empty($folder) && $folder !== '.') {
		$slug = $folder;
	} else {
		$slug = basename($plugin_basename, '.php');
	}

	$name = str_replace(array('-', '_'), ' ', $slug);
	return ucwords($name);
}

/**
 * Validate plugin basename
 *
 * @param  string  $basename Plugin basename
 * @return boolean
 */
function owm_validate_plugin_basename($basename)
{
	if (($plugins = get_plugins())) {
		foreach ($plugins as $plugin => $info) {
			if ($plugin === $basename) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Validate theme basename
 *
 * @param  string  $basename Theme basename
 * @return boolean
 */
function owm_validate_theme_basename($basename)
{
	if (($themes = search_theme_directories())) {
		foreach ($themes as $theme => $info) {
			if ($info['theme_file'] === $basename) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Flush WP options cache
 *
 * @return void
 */
function owm_cache_flush()
{
	// Initialize WP cache
	wp_cache_init();

	// Flush WP cache
	wp_cache_flush();

	// Set WP cache
	wp_cache_set('alloptions', array(), 'options');
	wp_cache_set('notoptions', array(), 'options');

	// Delete WP cache
	wp_cache_delete('alloptions', 'options');
	wp_cache_delete('notoptions', 'options');

	// Remove WP filters
	remove_all_filters('sanitize_option_siteurl');
	remove_all_filters('sanitize_option_home');
}

/**
 * Set URL scheme
 *
 * @param  string $url    URL value
 * @param  string $scheme URL scheme
 * @return string
 */
function owm_url_scheme($url, $scheme = '')
{
	if (empty($scheme)) {
		return preg_replace('#^\w+://#', '//', $url);
	}

	return preg_replace('#^\w+://#', $scheme . '://', $url);
}

/**
 * Opens a file in specified mode
 *
 * @param  string   $file Path to the file to open
 * @param  string   $mode Mode in which to open the file
 * @return resource
 * @throws Owm_Not_Accessible_Exception
 */
function owm_open($file, $mode)
{
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct stream pointer required for chunked archive streaming.
	$file_handle = @fopen($file, $mode);
	if (false === $file_handle) {
		throw new Owm_Not_Accessible_Exception(
			wp_kses_post(
				sprintf(
					/* translators: 1: file path, 2: open mode */
					__('Unable to open %1$s with mode %2$s. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration'),
					esc_html($file),
					esc_html($mode)
				)
			)
		);
	}

	return $file_handle;
}

/**
 * Write contents to a file
 *
 * @param  resource $handle  File handle to write to
 * @param  string   $content Contents to write to the file
 * @return integer
 * @throws Owm_Not_Writable_Exception
 * @throws Owm_Quota_Exceeded_Exception
 */
function owm_write($handle, $content)
{
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Direct stream write required for chunked archive streaming.
	$write_result = @fwrite($handle, $content);
	if (false === $write_result) {
		if (($meta = stream_get_meta_data($handle))) {
			throw new Owm_Not_Writable_Exception(
				wp_kses_post(
					sprintf(
						/* translators: %s: file URI */
						__('Unable to write to: %s. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration'),
						esc_html($meta['uri'])
					)
				)
			);
		}
	} elseif (strlen($content) !== $write_result) {
		if (($meta = stream_get_meta_data($handle))) {
			throw new Owm_Quota_Exceeded_Exception(
				wp_kses_post(
					sprintf(
						/* translators: %s: file URI */
						__('Out of disk space. Unable to write to: %s. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration'),
						esc_html($meta['uri'])
					)
				)
			);
		}
	}

	return $write_result;
}

/**
 * Read contents from a file
 *
 * @param  resource $handle   File handle to read from
 * @param  string   $filesize File size
 * @return integer
 * @throws Owm_Not_Readable_Exception
 */
function owm_read($handle, $filesize)
{
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Direct stream read required for chunked archive streaming.
	$read_result = @fread($handle, $filesize);
	if (false === $read_result) {
		if (($meta = stream_get_meta_data($handle))) {
			throw new Owm_Not_Readable_Exception(
				wp_kses_post(
					sprintf(
						/* translators: %s: file URI */
						__('Unable to read file: %s. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration'),
						esc_html($meta['uri'])
					)
				)
			);
		}
	}

	return $read_result;
}

/**
 * Seeks on a file pointer
 *
 * @param  string  $handle File handle to seeks
 * @return integer
 */
function owm_seek($handle, $offset, $mode = SEEK_SET)
{
	$seek_result = @fseek($handle, $offset, $mode);
	if (-1 === $seek_result) {
		if (($meta = stream_get_meta_data($handle))) {
			throw new Owm_Not_Seekable_Exception(
				wp_kses_post(
					sprintf(
						/* translators: 1: byte offset, 2: file URI */
						__('Unable to seek to offset %1$d on %2$s. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration'),
						(int) $offset,
						esc_html($meta['uri'])
					)
				)
			);
		}
	}

	return $seek_result;
}

/**
 * Tells on a file pointer
 *
 * @param  string  $handle File handle to tells
 * @return integer
 */
function owm_tell($handle)
{
	$tell_result = @ftell($handle);
	if (false === $tell_result) {
		if (($meta = stream_get_meta_data($handle))) {
			throw new Owm_Not_Tellable_Exception(
				wp_kses_post(
					sprintf(
						/* translators: %s: file URI */
						__('Unable to get current pointer position of %s. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration'),
						esc_html($meta['uri'])
					)
				)
			);
		}
	}

	return $tell_result;
}

/**
 * Closes a file handle
 *
 * @param  resource $handle File handle to close
 * @return boolean
 */
function owm_close($handle)
{
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct stream close required for chunked archive streaming.
	return @fclose($handle);
}

/**
 * Deletes a file
 *
 * @param  string  $file Path to file to delete
 * @return boolean
 */
function owm_unlink($file)
{
	if (function_exists('wp_delete_file')) {
		return wp_delete_file($file);
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Fallback if wp_delete_file is unavailable.
	return @unlink($file);
}

/**
 * Sets modification time of a file
 *
 * @param  string  $file Path to file to change modification time
 * @param  integer $time File modification time
 * @return boolean
 */
function owm_touch($file, $mtime)
{
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Setting file mtime during archive extraction.
	return @touch($file, $mtime);
}

/**
 * Changes file mode
 *
 * @param  string  $file Path to file to change mode
 * @param  integer $time File mode
 * @return boolean
 */
function owm_chmod($file, $mode)
{
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Setting file permissions during archive extraction.
	return @chmod($file, $mode);
}

/**
 * Copies one file's contents to another
 *
 * @param  string $source_file      File to copy the contents from
 * @param  string $destination_file File to copy the contents to
 */
function owm_copy($source_file, $destination_file)
{
	$source_handle = owm_open($source_file, 'rb');
	$destination_handle = owm_open($destination_file, 'ab');
	$chunk_size = class_exists('Owm_Resource') ? Owm_Resource::chunk_size() : 524288;
	while ($buffer = owm_read($source_handle, $chunk_size)) {
		owm_write($destination_handle, $buffer);
	}
	owm_close($source_handle);
	owm_close($destination_handle);
}

/**
 * Get the size of file in bytes
 *
 * This method supports files > 2GB on PHP x86
 *
 * @param string  $file_path Path to the file
 * @param boolean $as_string Return the filesize as string instead of BigInteger
 *
 * @return mixed Math_BigInteger|string|null
 */
function owm_filesize($file_path, $as_string = true)
{
	$chunk_size = 2000000; // 2MB
	$file_size = new Math_BigInteger(0);

	try {
		$file_handle = owm_open($file_path, 'rb');

		while (!feof($file_handle)) {
			$bytes = owm_read($file_handle, $chunk_size);
			$file_size = $file_size->add(new Math_BigInteger(strlen($bytes)));
		}

		owm_close($file_handle);

		return $as_string ? $file_size->toString() : $file_size;
	} catch (Exception $e) {
		return null;
	}
}

/**
 * Return the smaller of two numbers
 *
 * @param Math_BigInteger $a First number
 * @param Math_BigInteger $b Second number
 *
 * @return Math_BigInteger
 */
function owm_find_smaller_number(Math_BigInteger $a, Math_BigInteger $b)
{
	if ($a->compare($b) === -1) {
		return $a;
	}

	return $b;
}

/**
 * Check whether file size is supported by current PHP version
 *
 * @param  string  $file         Path to file
 * @param  integer $php_int_size Size of PHP integer
 * @return boolean $php_int_max  Max value of PHP integer
 */
function owm_is_filesize_supported($file, $php_int_size = PHP_INT_SIZE, $php_int_max = PHP_INT_MAX)
{
	$size_result = true;

	// Check whether file size is less than 2GB in PHP 32bits
	if ($php_int_size === 4) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Probe 32-bit filesize boundary.
		if (($file_handle = @fopen($file, 'r'))) {
			if (@fseek($file_handle, $php_int_max, SEEK_SET) !== -1) {
				if (@fgetc($file_handle) !== false) {
					$size_result = false;
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close probe handle.
			@fclose($file_handle);
		}
	}

	return $size_result;
}

/**
 * Wrapper around fseek
 *
 * This function works with offsets that are > PHP_INT_MAX
 *
 * @param resource        $file_handle Handle to the file
 * @param Math_BigInteger $offset      Offset of the file
 */
function owm_fseek($file_handle, Math_BigInteger $offset)
{
	$chunk_size = owm_find_smaller_number(new Math_BigInteger(2000000), $offset);
	while (!feof($file_handle) && $offset->toString() != '0') {
		$bytes = owm_read($file_handle, $chunk_size->toInteger());
		$offset = $offset->subtract(new Math_BigInteger(strlen($bytes)));
		$chunk_size = owm_find_smaller_number($chunk_size, $offset);
	}
}

/**
 * Sanitize request parameters recursively
 *
 * @param  mixed $input Input value or array
 * @return mixed
 */
function owm_sanitize_params($input)
{
	if (is_array($input)) {
		$sanitized = array();
		foreach ($input as $key => $value) {
			$clean_key = is_int($key) ? $key : sanitize_text_field((string) $key);
			$sanitized[$clean_key] = owm_sanitize_params($value);
		}
		return $sanitized;
	}

	if (is_string($input)) {
		return sanitize_text_field($input);
	}

	if (is_int($input) || is_float($input) || is_bool($input) || is_null($input)) {
		return $input;
	}

	return '';
}

/**
 * Verify request nonce, capability, and secret key
 *
 * @param  string $secret_key Secret key
 * @param  string $nonce      WordPress nonce
 * @param  string $capability Required capability when logged in
 * @return boolean
 * @throws Owm_Not_Valid_Secret_Key_Exception
 */
/**
 * Verify secret key, nonce, and capabilities
 *
 * @param  string $secret_key
 * @param  string $nonce
 * @param  string $capability
 * @return boolean
 * @throws Owm_Not_Valid_Secret_Key_Exception
 */
function owm_verify_secret_key($secret_key, $nonce = '', $capability = 'manage_options')
{
	$expected = get_option(OWM_SECRET_KEY);

	if (empty($nonce)) {
		if (isset($_POST['owm_nonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_POST['owm_nonce']));
		} elseif (isset($_GET['owm_nonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_GET['owm_nonce']));
		} elseif (isset($_REQUEST['_wpnonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
		}
	}

	// 1. Authenticated users: MUST pass nonce verification and possess required capability.
	// No secret-key bypass is permitted for logged-in users.
	if (function_exists('is_user_logged_in') && is_user_logged_in()) {
		if (!empty($nonce) && function_exists('wp_verify_nonce') && wp_verify_nonce($nonce, 'owm_ajax_nonce')) {
			if (function_exists('current_user_can') && (current_user_can($capability) || current_user_can('manage_options'))) {
				return true;
			}
			throw new Owm_Not_Valid_Secret_Key_Exception(
				wp_kses_post(__('You do not have sufficient permissions to perform this action.', 'onward-web-migration'))
			);
		}
		throw new Owm_Not_Valid_Secret_Key_Exception(
			wp_kses_post(__('Security check failed. Invalid nonce.', 'onward-web-migration'))
		);
	}

	// 2. Unauthenticated fallback: ONLY permitted during active multi-step database restore
	// (when table swap temporarily invalidates user auth session cookies in the browser).
	// Strictly restricted to owm_import action with priority >= 300.
	$action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
	if (!empty($expected) && !empty($secret_key) && $action === 'owm_import') {
		$is_valid_key = function_exists('hash_equals')
			? hash_equals((string) $expected, (string) $secret_key)
			: ((string) $secret_key === (string) $expected);

		if ($is_valid_key) {
			$priority = 0;
			if (isset($_POST['priority'])) {
				$priority = (int) $_POST['priority'];
			} elseif (isset($_GET['priority'])) {
				$priority = (int) $_GET['priority'];
			}

			// Only allow unauthenticated requests for post-database-swap steps (priority 300+)
			if ($priority >= 300) {
				return true;
			}
		}
	}

	throw new Owm_Not_Valid_Secret_Key_Exception(
		wp_kses_post(
			__('Unable to authenticate the request. Please refresh the page and try again.', 'onward-web-migration')
		)
	);
}

/**
 * Is scheduled backup?
 *
 * @return boolean
 */
function owm_is_scheduled_backup()
{
	// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Request flags check only.
	if (isset($_GET['owm_manual_export']) || isset($_POST['owm_manual_export'])) {
		return false;
	}

	if (isset($_GET['owm_manual_import']) || isset($_POST['owm_manual_import'])) {
		return false;
	}

	if (isset($_GET['owm_manual_restore']) || isset($_POST['owm_manual_restore'])) {
		return false;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

	if ((defined('DOING_AJAX') && DOING_AJAX) || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
		return false;
	}

	return true;
}

/**
 * PHP setup environment
 *
 * @return void
 */
function owm_setup_environment()
{
	// Disable WordPress generic HTML fatal error handler for migration AJAX calls so we can return rich JSON diagnostics
	if (!has_filter('wp_fatal_error_handler_enabled', '__return_false')) {
		add_filter('wp_fatal_error_handler_enabled', '__return_false', 9999);
	}

	// Set whether a client disconnect should abort script execution
	@ignore_user_abort(true);

	// Set maximum execution time
	// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for long-running backup and restore operations.
	@set_time_limit(0);

	// Set binary safe encoding
	if (@function_exists('mb_internal_encoding') && (@ini_get('mbstring.func_overload') & 2)) {
		@mb_internal_encoding('ISO-8859-1');
	}

	// Set error handler
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Custom error handler for JSON diagnostics.
	@set_error_handler('Owm_Handler::error');

	// Set shutdown handler
	@register_shutdown_function('Owm_Handler::shutdown');
}
