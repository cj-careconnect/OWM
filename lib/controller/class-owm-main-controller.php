<?php
/**

 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
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
*
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Main_Controller
{

	/**
	 * Main Application Controller
	 *
	 * @return Owm_Main_Controller
	 */
	public function __construct()
	{
		register_activation_hook(OWM_PLUGIN_BASENAME, array($this, 'activation_hook'));

		// Activate hooks
		$this->activate_actions();
		$this->activate_filters();
	}

	/**
	 * Activation hook callback
	 *
	 * @return void
	 */
	public function activation_hook()
	{
		if (is_dir(OWM_BACKUPS_PATH)) {
			$this->create_backups_htaccess(OWM_BACKUPS_HTACCESS);
			$this->create_backups_webconfig(OWM_BACKUPS_WEBCONFIG);
			$this->create_backups_index(OWM_BACKUPS_INDEX);
		}

		if (extension_loaded('litespeed')) {
			$this->create_litespeed_htaccess(OWM_WORDPRESS_HTACCESS);
		}
	}

	/**
	 * Initializes language domain for the plugin
	 *
	 * @return void
	 */
	public function load_text_domain()
	{
		// Automatic translation loading is handled by WordPress.org since 4.6.
	}

	/**
	 * Register listeners for actions
	 *
	 * @return void
	 */
	private function activate_actions()
	{
		// Execute core setup immediately
		$this->router();
		$this->setup_folders();
		$this->owm_commands();
		$this->owm_buttons();

		// Init (once WordPress pluggable & plugins are ready)
		add_action('plugins_loaded', array($this, 'init'), 1);
		add_action('admin_init', array($this, 'init'));

		// Router
		add_action('admin_init', array($this, 'router'));

		// Setup folders
		add_action('admin_init', array($this, 'setup_folders'));

		// Load text domain
		add_action('admin_init', array($this, 'load_text_domain'));

		// Onward Web Migration
		add_action('plugins_loaded', array($this, 'owm_loaded'), 10);

		// Export and import commands
		add_action('plugins_loaded', array($this, 'owm_commands'), 10);

		// Export and import buttons
		add_action('plugins_loaded', array($this, 'owm_buttons'), 10);

		// Register scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'register_scripts_and_styles'), 5);

		// Enqueue export scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'enqueue_export_scripts_and_styles'), 5);

		// Enqueue import scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'enqueue_import_scripts_and_styles'), 5);

		// Enqueue backups scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'enqueue_backups_scripts_and_styles'), 5);

		// Automated permalink flush on init (late priority so all plugins & CPTs are registered)
		add_action('init', array($this, 'maybe_flush_permalinks'), 9999);
	}

	/**
	 * Register listeners for filters
	 *
	 * @return void
	 */
	private function activate_filters()
	{
		// Add links to plugin list page
		add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);

		// Add custom schedules
		add_filter('cron_schedules', array($this, 'add_cron_schedules'), 9999);
	}

	/**
	 * Export and import commands
	 *
	 * @return void
	 */
	public function owm_commands()
	{
		// Add export commands
		add_filter('owm_export', 'Owm_Export_Init::execute', 5);
		add_filter('owm_export', 'Owm_Export_Compatibility::execute', 5);
		add_filter('owm_export', 'Owm_Export_Archive::execute', 10);
		add_filter('owm_export', 'Owm_Export_Config::execute', 50);
		add_filter('owm_export', 'Owm_Export_Config_File::execute', 60);
		add_filter('owm_export', 'Owm_Export_Enumerate::execute', 100);
		add_filter('owm_export', 'Owm_Export_Content::execute', 150);
		add_filter('owm_export', 'Owm_Export_Database::execute', 200);
		add_filter('owm_export', 'Owm_Export_Database_File::execute', 220);
		add_filter('owm_export', 'Owm_Export_Download::execute', 250);
		add_filter('owm_export', 'Owm_Export_Clean::execute', 300);

		// Add import commands
		add_filter('owm_import', 'Owm_Import_Upload::execute', 5);
		add_filter('owm_import', 'Owm_Import_Compatibility::execute', 10);
		add_filter('owm_import', 'Owm_Import_Validate::execute', 50);
		add_filter('owm_import', 'Owm_Import_Confirm::execute', 100);
		add_filter('owm_import', 'Owm_Import_Blogs::execute', 150);
		add_filter('owm_import', 'Owm_Import_Enumerate::execute', 200);
		add_filter('owm_import', 'Owm_Import_Content::execute', 250);
		// Mu-plugins extraction disabled for WordPress.org security compliance
		add_filter('owm_import', 'Owm_Import_Database::execute', 300);
		add_filter('owm_import', 'Owm_Import_Done::execute', 350);
		add_filter('owm_import', 'Owm_Import_Clean::execute', 400);
	}

	/**
	 * Export and import buttons
	 *
	 * @return void
	 */
	public function owm_buttons()
	{
		// Add export buttons
		add_filter('owm_export_buttons', 'Owm_Export_Controller::buttons');

		// Add import buttons
		add_filter('owm_import_buttons', 'Owm_Import_Controller::buttons');
	}

	/**
	 * Onward Web Migration loaded
	 *
	 * @return void
	 */
	public function owm_loaded()
	{
		if (is_multisite()) {
			add_action('network_admin_menu', array($this, 'admin_menu'));
			add_action('admin_menu', array($this, 'admin_menu'));
		} else {
			add_action('admin_menu', array($this, 'admin_menu'));
		}

		// Add HTTP export headers
		add_filter('owm_http_export_headers', 'Owm_Export_Controller::http_export_headers');

		// Add HTTP import headers
		add_filter('owm_http_import_headers', 'Owm_Import_Controller::http_import_headers');

		// Add chunk size limit
		add_filter('owm_max_chunk_size', 'Owm_Import_Controller::max_chunk_size');

		// Adaptive per-request timeout based on server max_execution_time
		add_filter('owm_completed_timeout', 'Owm_Resource::timeout', 5);
	}

	/**
	 * Create folders and files needed for plugin operation, if they don't exist
	 *
	 * @return void
	 */
	public function setup_folders()
	{
		// Check if storage folder is created
		if (!is_dir(OWM_STORAGE_PATH)) {
			$this->create_storage_folder(OWM_STORAGE_PATH);
		}

		// Check if backups folder is created
		if (!is_dir(OWM_BACKUPS_PATH)) {
			$this->create_backups_folder(OWM_BACKUPS_PATH);
		}

		// Check if index.php is created in storage folder
		if (!is_file(OWM_STORAGE_INDEX)) {
			$this->create_storage_index(OWM_STORAGE_INDEX);
		}

		// Check if .htaccess is created in storage folder
		if (defined('OWM_STORAGE_HTACCESS') && !is_file(OWM_STORAGE_HTACCESS)) {
			$this->create_storage_htaccess(OWM_STORAGE_HTACCESS);
		}

		// Check if web.config is created in storage folder
		if (defined('OWM_STORAGE_WEBCONFIG') && !is_file(OWM_STORAGE_WEBCONFIG)) {
			$this->create_storage_webconfig(OWM_STORAGE_WEBCONFIG);
		}

		// Check if index.php is created in backups folder
		if (!is_file(OWM_BACKUPS_INDEX)) {
			$this->create_backups_index(OWM_BACKUPS_INDEX);
		}

		// Check if .htaccess is created in backups folder
		if (!is_file(OWM_BACKUPS_HTACCESS)) {
			$this->create_backups_htaccess(OWM_BACKUPS_HTACCESS);
		}

		// Check if web.config is created in backups folder
		if (!is_file(OWM_BACKUPS_WEBCONFIG)) {
			$this->create_backups_webconfig(OWM_BACKUPS_WEBCONFIG);
		}
	}

	/**
	 * Create storage folder
	 *
	 * @param  string Path to folder
	 * @return void
	 */
	public function create_storage_folder($path)
	{
		if (!Owm_Directory::create($path)) {
			if (is_multisite()) {
				return add_action('network_admin_notices', array($this, 'storage_path_notice'));
			} else {
				return add_action('admin_notices', array($this, 'storage_path_notice'));
			}
		}
	}

	/**
	 * Create backups folder
	 *
	 * @param  string Path to folder
	 * @return void
	 */
	public function create_backups_folder($path)
	{
		if (!Owm_Directory::create($path)) {
			if (is_multisite()) {
				return add_action('network_admin_notices', array($this, 'backups_path_notice'));
			} else {
				return add_action('admin_notices', array($this, 'backups_path_notice'));
			}
		}
	}

	/**
	 * Create storage index.php file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_storage_htaccess($path)
	{
		Owm_File_Htaccess::create($path);
	}

	public function create_storage_webconfig($path)
	{
		Owm_File_Webconfig::create($path);
	}

	public function create_storage_index($path)
	{
		if (!Owm_File_Index::create($path)) {
			if (is_multisite()) {
				return add_action('network_admin_notices', array($this, 'storage_index_notice'));
			} else {
				return add_action('admin_notices', array($this, 'storage_index_notice'));
			}
		}
	}

	/**
	 * Create backups .htaccess file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_backups_htaccess($path)
	{
		if (!Owm_File_Htaccess::create($path)) {
			if (is_multisite()) {
				return add_action('network_admin_notices', array($this, 'backups_htaccess_notice'));
			} else {
				return add_action('admin_notices', array($this, 'backups_htaccess_notice'));
			}
		}
	}

	/**
	 * Create backups web.config file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_backups_webconfig($path)
	{
		if (!Owm_File_Webconfig::create($path)) {
			if (is_multisite()) {
				return add_action('network_admin_notices', array($this, 'backups_webconfig_notice'));
			} else {
				return add_action('admin_notices', array($this, 'backups_webconfig_notice'));
			}
		}
	}

	/**
	 * Create backups index.php file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_backups_index($path)
	{
		if (!Owm_File_Index::create($path)) {
			if (is_multisite()) {
				return add_action('network_admin_notices', array($this, 'backups_index_notice'));
			} else {
				return add_action('admin_notices', array($this, 'backups_index_notice'));
			}
		}
	}

	/**
	 * If the "noabort" environment variable has been set,
	 * the script will continue to run even though the connection has been broken
	 *
	 * @return void
	 */
	public function create_litespeed_htaccess($path)
	{
		if (!Owm_File_Htaccess::litespeed($path)) {
			if (is_multisite()) {
				return add_action('network_admin_notices', array($this, 'wordpress_htaccess_notice'));
			} else {
				return add_action('admin_notices', array($this, 'wordpress_htaccess_notice'));
			}
		}
	}

	/**
	 * Display multisite notice
	 *
	 * @return void
	 */
	public function multisite_notice()
	{
		Owm_Template::render('main/multisite-notice');
	}

	/**
	 * Display notice for storage directory
	 *
	 * @return void
	 */
	public function storage_path_notice()
	{
		Owm_Template::render('main/storage-path-notice');
	}

	/**
	 * Display notice for index file in storage directory
	 *
	 * @return void
	 */
	public function storage_index_notice()
	{
		Owm_Template::render('main/storage-index-notice');
	}

	/**
	 * Display notice for backups directory
	 *
	 * @return void
	 */
	public function backups_path_notice()
	{
		Owm_Template::render('main/backups-path-notice');
	}

	/**
	 * Display notice for .htaccess file in backups directory
	 *
	 * @return void
	 */
	public function backups_htaccess_notice()
	{
		Owm_Template::render('main/backups-htaccess-notice');
	}

	/**
	 * Display notice for web.config file in backups directory
	 *
	 * @return void
	 */
	public function backups_webconfig_notice()
	{
		Owm_Template::render('main/backups-webconfig-notice');
	}

	/**
	 * Display notice for index file in backups directory
	 *
	 * @return void
	 */
	public function backups_index_notice()
	{
		Owm_Template::render('main/backups-index-notice');
	}

	/**
	 * Display notice for .htaccess file in WordPress directory
	 *
	 * @return void
	 */
	public function wordpress_htaccess_notice()
	{
		Owm_Template::render('main/wordpress-htaccess-notice');
	}

	/**
	 * Add links to plugin list page
	 *
	 * @return array
	 */
	public function plugin_row_meta($links, $file)
	{
		if ($file == OWM_PLUGIN_BASENAME) {
			$links[] = Owm_Template::get_content('main/get-support');
		}

		return $links;
	}

	/**
	 * Register plugin menus
	 *
	 * @return void
	 */
	public function admin_menu()
	{
		// Top-level WP Migration menu
		add_menu_page(
			'Onward Web Migration',
			'Onward Web Migration',
			'export',
			'owm_export',
			'Owm_Export_Controller::index',
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIiB3aWR0aD0iNTEyIiBoZWlnaHQ9IjUxMiI+CiAgPGRlZnM+CiAgICA8IS0tIFByaW1hcnkgTW9kZXJuIFdvcmRQcmVzcy1JbnNwaXJlZCBPY2VhbiBHcmFkaWVudCAtLT4KICAgIDxsaW5lYXJHcmFkaWVudCBpZD0iYmdHcmFkIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj4KICAgICAgPHN0b3Agb2Zmc2V0PSIwJSIgc3RvcC1jb2xvcj0iIzM4NThFOSIgLz4KICAgICAgPHN0b3Agb2Zmc2V0PSIxMDAlIiBzdG9wLWNvbG9yPSIjMUUzNUE4IiAvPgogICAgPC9saW5lYXJHcmFkaWVudD4KCiAgICA8IS0tIFN1YnRsZSBBY2NlbnQgR3JhZGllbnQgZm9yIE1pZ3JhdGlvbiBBcnJvd3MgLS0+CiAgICA8bGluZWFyR3JhZGllbnQgaWQ9ImFjY2VudEdyYWQiIHgxPSIwJSIgeTE9IjAlIiB4Mj0iMTAwJSIgeTI9IjEwMCUiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjMzhCREY4IiAvPgogICAgICA8c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiMwMjg0QzciIC8+CiAgICA8L2xpbmVhckdyYWRpZW50PgoKICAgIDwhLS0gQ3Jpc3AgRHJvcCBTaGFkb3cgZm9yIENlbnRyYWwgRWxlbWVudHMgLS0+CiAgICA8ZmlsdGVyIGlkPSJkcm9wU2hhZG93IiB4PSItMTUlIiB5PSItMTUlIiB3aWR0aD0iMTMwJSIgaGVpZ2h0PSIxMzAlIj4KICAgICAgPGZlRHJvcFNoYWRvdyBkeD0iMCIgZHk9IjE2IiBzdGREZXZpYXRpb249IjE2IiBmbG9vZC1jb2xvcj0iIzBGMTcyQSIgZmxvb2Qtb3BhY2l0eT0iMC4zIiAvPgogICAgPC9maWx0ZXI+CgogICAgPCEtLSBJbm5lciBHbHlwaCBHbG93IC0tPgogICAgPGZpbHRlciBpZD0iaW5uZXJHbG93IiB4PSItMjAlIiB5PSItMjAlIiB3aWR0aD0iMTQwJSIgaGVpZ2h0PSIxNDAlIj4KICAgICAgPGZlRHJvcFNoYWRvdyBkeD0iMCIgZHk9IjYiIHN0ZERldmlhdGlvbj0iOCIgZmxvb2QtY29sb3I9IiMwRjE3MkEiIGZsb29kLW9wYWNpdHk9IjAuMzUiIC8+CiAgICA8L2ZpbHRlcj4KCiAgICA8c3R5bGU+CiAgICAgIC8qIFNtb290aCBjb250aW51b3VzIG9yYml0YWwgcm90YXRpb24gZm9yIHN5bmMgYXJyb3dzICovCiAgICAgIC5zeW5jLXJpbmcgewogICAgICAgIHRyYW5zZm9ybS1vcmlnaW46IDI1NnB4IDI1NnB4OwogICAgICAgIGFuaW1hdGlvbjogb3JiaXRTcGluIDMuMnMgY3ViaWMtYmV6aWVyKDAuNjUsIDAsIDAuMzUsIDEpIGluZmluaXRlOwogICAgICB9CgogICAgICAvKiBTdWJ0bGUgYnJlYXRoaW5nIHB1bHNlIG9uIHRoZSBjZW50cmFsICJXIiBtb25vZ3JhbSAqLwogICAgICAudy1tb25vZ3JhbSB7CiAgICAgICAgdHJhbnNmb3JtLW9yaWdpbjogMjU2cHggMjU4cHg7CiAgICAgICAgYW5pbWF0aW9uOiBnbHlwaFB1bHNlIDMuMnMgZWFzZS1pbi1vdXQgaW5maW5pdGU7CiAgICAgIH0KCiAgICAgIEBrZXlmcmFtZXMgb3JiaXRTcGluIHsKICAgICAgICAwJSB7CiAgICAgICAgICB0cmFuc2Zvcm06IHJvdGF0ZSgwZGVnKTsKICAgICAgICB9CiAgICAgICAgNDUlLCA1NSUgewogICAgICAgICAgdHJhbnNmb3JtOiByb3RhdGUoMTgwZGVnKTsKICAgICAgICB9CiAgICAgICAgMTAwJSB7CiAgICAgICAgICB0cmFuc2Zvcm06IHJvdGF0ZSgzNjBkZWcpOwogICAgICAgIH0KICAgICAgfQoKICAgICAgQGtleWZyYW1lcyBnbHlwaFB1bHNlIHsKICAgICAgICAwJSwgMTAwJSB7CiAgICAgICAgICB0cmFuc2Zvcm06IHNjYWxlKDEpOwogICAgICAgICAgb3BhY2l0eTogMTsKICAgICAgICB9CiAgICAgICAgNTAlIHsKICAgICAgICAgIHRyYW5zZm9ybTogc2NhbGUoMS4wNDUpOwogICAgICAgICAgb3BhY2l0eTogMC45NDsKICAgICAgICB9CiAgICAgIH0KCiAgICAgIC8qIFJlc3BlY3QgcmVkdWNlZC1tb3Rpb24gYWNjZXNzaWJpbGl0eSBwcmVmZXJlbmNlcyAqLwogICAgICBAbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246IHJlZHVjZSkgewogICAgICAgIC5zeW5jLXJpbmcsCiAgICAgICAgLnctbW9ub2dyYW0gewogICAgICAgICAgYW5pbWF0aW9uOiBub25lOwogICAgICAgIH0KICAgICAgfQogICAgPC9zdHlsZT4KICA8L2RlZnM+CgogIDwhLS0gSGlnaC1SZXNvbHV0aW9uIEFwcCBJY29uIFNxdWlyY2xlIEJhc2UgKDUxMng1MTIpIC0tPgogIDxyZWN0IHg9IjMyIiB5PSIzMiIgd2lkdGg9IjQ0OCIgaGVpZ2h0PSI0NDgiIHJ4PSIxMDgiIGZpbGw9InVybCgjYmdHcmFkKSIgLz4KCiAgPCEtLSBTdWJ0bGUgSW5uZXIgSGlnaGxpZ2h0IEJvcmRlciAtLT4KICA8cmVjdCB4PSIzNiIgeT0iMzYiIHdpZHRoPSI0NDAiIGhlaWdodD0iNDQwIiByeD0iMTA0IiBmaWxsPSJub25lIiBzdHJva2U9IiNGRkZGRkYiIHN0cm9rZS1vcGFjaXR5PSIwLjIiIHN0cm9rZS13aWR0aD0iNCIgLz4KCiAgPCEtLSBBbmltYXRlZCBPdXRlciBDaXJjdWxhciBNaWdyYXRpb24gLyBCYWNrdXAgQXJyb3dzIC0tPgogIDxnIGNsYXNzPSJzeW5jLXJpbmciIGZpbGw9Im5vbmUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgZmlsdGVyPSJ1cmwoI2Ryb3BTaGFkb3cpIj4KICAgIDwhLS0gVG9wLVJpZ2h0IEN1cnZlZCBBcnJvdyAoQmFja3VwIC8gVXBsb2FkKSAtLT4KICAgIDxwYXRoIGQ9Ik0gMTQ4IDE3NiBBIDEzMiAxMzIgMCAwIDEgMzY4IDE4OCIgc3Ryb2tlPSIjRkZGRkZGIiBzdHJva2Utd2lkdGg9IjI4IiBzdHJva2Utb3BhY2l0eT0iMC45NSIgLz4KICAgIDxwb2x5bGluZSBwb2ludHM9IjMyOCwxOTIgMzcyLDE5NiAzNzYsMTUyIiBzdHJva2U9IiNGRkZGRkYiIHN0cm9rZS13aWR0aD0iMjgiIHN0cm9rZS1vcGFjaXR5PSIwLjk1IiAvPgoKICAgIDwhLS0gQm90dG9tLUxlZnQgQ3VydmVkIEFycm93IChSZXN0b3JlIC8gRG93bmxvYWQpIC0tPgogICAgPHBhdGggZD0iTSAzNjQgMzM2IEEgMTMyIDEzMiAwIDAgMSAxNDQgMzI0IiBzdHJva2U9InVybCgjYWNjZW50R3JhZCkiIHN0cm9rZS13aWR0aD0iMjgiIC8+CiAgICA8cG9seWxpbmUgcG9pbnRzPSIxODQsMzIwIDE0MCwzMTYgMTM2LDM2MCIgc3Ryb2tlPSJ1cmwoI2FjY2VudEdyYWQpIiBzdHJva2Utd2lkdGg9IjI4IiAvPgogIDwvZz4KCiAgPCEtLSBDZW50cmFsIEdlb21ldHJpYyAiVyIgTW9ub2dyYW0gKFdvcmRQcmVzcyAvIE9wZW4gV1ApIC0tPgogIDxnIGNsYXNzPSJ3LW1vbm9ncmFtIiBmaWx0ZXI9InVybCgjaW5uZXJHbG93KSI+CiAgICA8cGF0aCBkPSJNIDE2MCAyMDggTCAxOTQgMzA4IEwgMjI4IDIyOCBMIDI1NiAyODggTCAyODQgMjI4IEwgMzE4IDMwOCBMIDM1MiAyMDgiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI0ZGRkZGRiIgc3Ryb2tlLXdpZHRoPSIzMCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIiAvPgogIDwvZz4KPC9zdmc+',
			'76.295'
		);

		// Sub-level Export menu
		add_submenu_page(
			'owm_export',
			__('Export', 'onward-web-migration'),
			__('Export', 'onward-web-migration'),
			'export',
			'owm_export',
			'Owm_Export_Controller::index'
		);

		// Sub-level Import menu
		add_submenu_page(
			'owm_export',
			__('Import', 'onward-web-migration'),
			__('Import', 'onward-web-migration'),
			'import',
			'owm_import',
			'Owm_Import_Controller::index'
		);

		// Sub-level Backups menu
		add_submenu_page(
			'owm_export',
			__('Backups', 'onward-web-migration'),
			__('Backups', 'onward-web-migration'),
			'import',
			'owm_backups',
			'Owm_Backups_Controller::index'
		);
	}

	/**
	 * Register scripts and styles
	 *
	 * @return void
	 */
	public function register_scripts_and_styles()
	{
		if (is_rtl()) {
			wp_register_style(
				'owm_onward',
				Owm_Template::asset_link('css/owm.min.rtl.css'),
				array(),
				OWM_VERSION
			);
			wp_register_style(
				'owm_omini',
				Owm_Template::asset_link('css/owm.min.rtl.css'),
				array(),
				OWM_VERSION
			);
		} else {
			wp_register_style(
				'owm_onward',
				Owm_Template::asset_link('css/owm.min.css'),
				array(),
				OWM_VERSION
			);
			wp_register_style(
				'owm_omini',
				Owm_Template::asset_link('css/owm.min.css'),
				array(),
				OWM_VERSION
			);
		}

		wp_register_script(
			'owm_util',
			Owm_Template::asset_link('javascript/util.min.js'),
			array('jquery'),
			OWM_VERSION,
			true
		);

		wp_register_script(
			'owm_browser_zip',
			Owm_Template::asset_link('javascript/browser-zip.js'),
			array(),
			OWM_VERSION,
			true
		);

		wp_register_script(
			'owm_unified_workspace',
			Owm_Template::asset_link('javascript/unified-workspace.js'),
			array('jquery', 'owm_util', 'owm_browser_zip'),
			OWM_VERSION,
			true
		);

		// Enqueue admin menu icon style via wp_add_inline_style
		$menu_icon_url = esc_url(wp_make_link_relative(OWM_URL) . '/lib/view/assets/img/logo.svg');
		$menu_css = "
			.toplevel_page_owm_export > div.wp-menu-image:before {
				position: relative;
				display: inline-block;
				content: '';
				background: url('{$menu_icon_url}') no-repeat center center;
				background-size: 20px 20px !important;
				width: 20px;
				height: 20px;
				speak: none !important;
				font-style: normal !important;
				font-weight: normal !important;
				font-variant: normal !important;
				text-transform: none !important;
				line-height: 1 !important;
				-webkit-font-smoothing: antialiased !important;
				-moz-osx-font-smoothing: grayscale !important;
			}
			.toplevel_page_owm_export > div.wp-menu-image img {
				width: 20px;
				height: 20px;
				padding-top: 7px;
			}
			.wp-menu-open.toplevel_page_owm_export,
			.wp-menu-open.toplevel_page_owm_export > a {
				background-color: #1E3A8A !important;
			}
		";
		wp_add_inline_style('admin-menu', $menu_css);
	}

	/**
	 * Enqueue unified workspace configuration and script
	 *
	 * @param  string $initial_view Initial active view ('all', 'export', 'import', 'backups')
	 * @return void
	 */
	public function enqueue_unified_workspace_script($initial_view = 'all')
	{
		$secret_key = get_option(OWM_SECRET_KEY);
		if (empty($secret_key) && function_exists('wp_generate_password')) {
			$secret_key = wp_generate_password(12, false);
			update_option(OWM_SECRET_KEY, $secret_key);
		}

		$site_url  = home_url();
		$site_host = wp_parse_url($site_url, PHP_URL_HOST);
		if (empty($site_host) && isset($_SERVER['HTTP_HOST'])) {
			$site_host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
		}
		if (empty($site_host)) {
			$site_host = 'localhost';
		}

		$backups_model      = new Owm_Backups;
		$backups            = $backups_model->get_files();
		$initial_backups_js = array();

		if (!empty($backups)) {
			foreach ($backups as $index => $bk) {
				$filename = basename($bk['filename']);
				$mtime    = isset($bk['mtime']) && $bk['mtime'] ? (int) $bk['mtime'] : time();
				/* translators: %s: relative time difference */
				$date_ago  = function_exists('human_time_diff') ? sprintf(__('%s ago', 'onward-web-migration'), human_time_diff($mtime)) : gmdate('Y-m-d', $mtime);
				$timestamp = gmdate('M d, Y • h:i A', $mtime);
				$size_mb   = isset($bk['size']) && $bk['size'] ? round($bk['size'] / (1024 * 1024), 2) : 0;

				$download_url = function_exists('owm_backup_url') ? owm_backup_url(array('archive' => $bk['filename'])) : '#';

				$initial_backups_js[] = array(
					'id'          => 'bk-' . ($index + 1),
					'name'        => $filename,
					'rawFilename' => $bk['filename'],
					'date'        => $date_ago,
					'timestamp'   => $timestamp,
					'sizeMB'      => $size_mb,
					'type'        => 'Server Snapshot',
					'downloadUrl' => $download_url,
				);
			}
		}

		$resource_config = class_exists('Owm_Resource') ? Owm_Resource::client_config() : array(
			'profile'        => 'normal',
			'mbps'           => 50,
			'prefer_browser' => false,
			'zlib'           => true,
		);

		$initial_debug_report = class_exists('Owm_Handler') && method_exists('Owm_Handler', 'get_debug_report')
			? Owm_Handler::get_debug_report()
			: array();

		$owm_config = array(
			'ajaxUrl'      => wp_make_link_relative(admin_url('admin-ajax.php')),
			'secretKey'    => $secret_key,
			'nonce'        => wp_create_nonce('owm_ajax_nonce'),
			'initialView'  => $initial_view,
			'siteHost'     => $site_host,
			'backups'      => $initial_backups_js,
			'chunkSize'    => apply_filters('owm_max_chunk_size', OWM_MAX_CHUNK_SIZE),
			'maxFileSize'  => apply_filters('owm_max_file_size', OWM_MAX_FILE_SIZE),
			'resource'     => $resource_config,
			'initialDebug' => $initial_debug_report,
		);

		wp_enqueue_script('owm_unified_workspace');
		wp_localize_script('owm_unified_workspace', 'owmConfig', $owm_config);
	}

	/**
	 * Enqueue scripts and styles for Export Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_export_scripts_and_styles($hook)
	{
		if (stripos($hook, 'owm_export') === false) {
			return;
		}

		// We don't want heartbeat to occur when exporting
		wp_deregister_script('heartbeat');

		// We don't want auth check for monitoring whether the user is still logged in
		remove_action('admin_enqueue_scripts', 'wp_auth_check_load');

		if (is_rtl()) {
			wp_enqueue_style(
				'owm_export',
				Owm_Template::asset_link('css/export.min.rtl.css'),
				array(),
				OWM_VERSION
			);
		} else {
			wp_enqueue_style(
				'owm_export',
				Owm_Template::asset_link('css/export.min.css'),
				array(),
				OWM_VERSION
			);
		}

		wp_enqueue_style(
			'owm_wp_sync_backup',
			Owm_Template::asset_link('css/wp-sync-backup.css'),
			array(),
			OWM_VERSION
		);

		wp_enqueue_script(
			'owm_export',
			Owm_Template::asset_link('javascript/export.min.js'),
			array('owm_util', 'owm_browser_zip'),
			OWM_VERSION,
			true
		);


		$nonce = wp_create_nonce('owm_ajax_nonce');

		wp_localize_script('owm_export', 'owm_export', array(
			'ajax' => array(
				'url' => wp_make_link_relative(admin_url('admin-ajax.php?action=owm_export')),
			),
			'status' => array(
				'url' => wp_make_link_relative(add_query_arg(array('secret_key' => get_option(OWM_SECRET_KEY), 'owm_nonce' => $nonce), admin_url('admin-ajax.php?action=owm_status'))),
			),
			'secret_key' => get_option(OWM_SECRET_KEY),
			'owm_nonce' => $nonce,
		));

		wp_localize_script('owm_export', 'owm_locale', array(
			'stop_exporting_your_website' => __('You are about to stop exporting your website, are you sure?', 'onward-web-migration'),
			'preparing_to_export' => __('Preparing to export...', 'onward-web-migration'),
			'unable_to_export' => __('Unable to export', 'onward-web-migration'),
			'unable_to_start_the_export' => __('Unable to start the export. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_run_the_export' => __('Unable to run the export. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_stop_the_export' => __('Unable to stop the export. Refresh the page and try again', 'onward-web-migration'),
			'please_wait_stopping_the_export' => __('Please wait, stopping the export...', 'onward-web-migration'),
			'close_export' => __('Close', 'onward-web-migration'),
			'stop_export' => __('Stop export', 'onward-web-migration'),
		));

		$this->enqueue_unified_workspace_script('all');
	}

	/**
	 * Enqueue scripts and styles for Import Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_import_scripts_and_styles($hook)
	{
		if (stripos($hook, 'owm_import') === false) {
			return;
		}

		// We don't want heartbeat to occur when importing
		wp_deregister_script('heartbeat');

		// We don't want auth check for monitoring whether the user is still logged in
		remove_action('admin_enqueue_scripts', 'wp_auth_check_load');

		if (is_rtl()) {
			wp_enqueue_style(
				'owm_import',
				Owm_Template::asset_link('css/import.min.rtl.css'),
				array(),
				OWM_VERSION
			);
		} else {
			wp_enqueue_style(
				'owm_import',
				Owm_Template::asset_link('css/import.min.css'),
				array(),
				OWM_VERSION
			);
		}

		wp_enqueue_style(
			'owm_wp_sync_backup',
			Owm_Template::asset_link('css/wp-sync-backup.css'),
			array(),
			OWM_VERSION
		);

		wp_enqueue_script(
			'owm_import',
			Owm_Template::asset_link('javascript/import.min.js'),
			array('owm_util', 'owm_browser_zip'),
			OWM_VERSION,
			true
		);


		$nonce = wp_create_nonce('owm_ajax_nonce');

		wp_localize_script('owm_import', 'owm_uploader', array(
			'chunk_size' => apply_filters('owm_max_chunk_size', OWM_MAX_CHUNK_SIZE),
			'max_retries' => apply_filters('owm_max_chunk_retries', OWM_MAX_CHUNK_RETRIES),
			'url' => wp_make_link_relative(admin_url('admin-ajax.php?action=owm_import')),
			'params' => array(
				'priority' => 5,
				'secret_key' => get_option(OWM_SECRET_KEY),
				'owm_nonce' => $nonce,
			),
			'filters' => array(
				'owm_archive_extension' => array('zip'),
				'owm_archive_size' => apply_filters('owm_max_file_size', OWM_MAX_FILE_SIZE),
			),
		));

		wp_localize_script('owm_import', 'owm_import', array(
			'ajax' => array(
				'url' => wp_make_link_relative(admin_url('admin-ajax.php?action=owm_import')),
			),
			'status' => array(
				'url' => wp_make_link_relative(add_query_arg(array('secret_key' => get_option(OWM_SECRET_KEY), 'owm_nonce' => $nonce), admin_url('admin-ajax.php?action=owm_status'))),
			),
			'secret_key' => get_option(OWM_SECRET_KEY),
			'owm_nonce' => $nonce,
		));

		wp_localize_script('owm_import', 'owm_locale', array(
			'stop_importing_your_website' => __('You are about to stop importing your website, are you sure?', 'onward-web-migration'),
			'preparing_to_import' => __('Preparing to import...', 'onward-web-migration'),
			'unable_to_import' => __('Unable to import', 'onward-web-migration'),
			'unable_to_start_the_import' => __('Unable to start the import. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_confirm_the_import' => __('Unable to confirm the import. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_prepare_blogs_on_import' => __('Unable to prepare blogs on import. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_stop_the_import' => __('Unable to stop the import. Refresh the page and try again', 'onward-web-migration'),
			'please_wait_stopping_the_export' => __('Please wait, stopping the import...', 'onward-web-migration'),
			'close_import' => __('Close', 'onward-web-migration'),
			'stop_import' => __('Stop import', 'onward-web-migration'),
			'confirm_import' => __('Proceed', 'onward-web-migration'),
			'plugins_to_restore' => __('Plugins to restore', 'onward-web-migration'),
			'continue_import' => __('Continue', 'onward-web-migration'),
			'please_do_not_close_this_browser' => __('Please do not close this browser window or your import will fail', 'onward-web-migration'),
			'problem_while_uploading_your_file' => __('We are sorry, there seems to be a problem while uploading your file. Follow <a href="https://www.youtube.com/watch?v=mRp7qTFYKgs" target="_blank">this guide</a> to resolve it.', 'onward-web-migration'),
			'invalid_archive_extension' => __( 'The file type that you have tried to upload is not compatible with this plugin. Please ensure that your file is a <strong>.zip</strong> file that was created with the Onward Web Migration plugin. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration' ),
			'invalid_archive_size' => sprintf(
				/* translators: %s: maximum upload file size */
				__( 'The file that you are trying to import is over the maximum upload file size limit of <strong>%s</strong>.', 'onward-web-migration' ),
				size_format( apply_filters( 'owm_max_file_size', OWM_MAX_FILE_SIZE ) )
			),
		));

		$this->enqueue_unified_workspace_script('import');
	}

	/**
	 * Enqueue scripts and styles for Backups Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_backups_scripts_and_styles($hook)
	{
		if (stripos($hook, 'owm_backups') === false) {
			return;
		}

		// We don't want heartbeat to occur when restoring
		wp_deregister_script('heartbeat');

		// We don't want auth check for monitoring whether the user is still logged in
		remove_action('admin_enqueue_scripts', 'wp_auth_check_load');

		if (is_rtl()) {
			wp_enqueue_style(
				'owm_backups',
				Owm_Template::asset_link('css/backups.min.rtl.css'),
				array(),
				OWM_VERSION
			);
		} else {
			wp_enqueue_style(
				'owm_backups',
				Owm_Template::asset_link('css/backups.min.css'),
				array(),
				OWM_VERSION
			);
		}

		wp_enqueue_style(
			'owm_wp_sync_backup',
			Owm_Template::asset_link('css/wp-sync-backup.css'),
			array(),
			OWM_VERSION
		);

		wp_enqueue_script(
			'owm_backups',
			Owm_Template::asset_link('javascript/backups.min.js'),
			array('owm_util', 'owm_browser_zip'),
			OWM_VERSION,
			true
		);


		$nonce = wp_create_nonce('owm_ajax_nonce');

		wp_localize_script('owm_backups', 'owm_import', array(
			'ajax' => array(
				'url' => wp_make_link_relative(admin_url('admin-ajax.php?action=owm_import')),
			),
			'status' => array(
				'url' => wp_make_link_relative(add_query_arg(array('secret_key' => get_option(OWM_SECRET_KEY), 'owm_nonce' => $nonce), admin_url('admin-ajax.php?action=owm_status'))),
			),
			'secret_key' => get_option(OWM_SECRET_KEY),
			'owm_nonce' => $nonce,
		));

		wp_localize_script('owm_backups', 'owm_backups', array(
			'ajax' => array(
				'url' => wp_make_link_relative(admin_url('admin-ajax.php?action=owm_backups')),
			),
			'secret_key' => get_option(OWM_SECRET_KEY),
			'owm_nonce' => $nonce,
		));

		wp_localize_script('owm_backups', 'owm_locale', array(
			'stop_importing_your_website' => __('You are about to stop importing your website, are you sure?', 'onward-web-migration'),
			'preparing_to_import' => __('Preparing to import...', 'onward-web-migration'),
			'unable_to_import' => __('Unable to import', 'onward-web-migration'),
			'unable_to_start_the_import' => __('Unable to start the import. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_confirm_the_import' => __('Unable to confirm the import. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_prepare_blogs_on_import' => __('Unable to prepare blogs on import. Refresh the page and try again', 'onward-web-migration'),
			'unable_to_stop_the_import' => __('Unable to stop the import. Refresh the page and try again', 'onward-web-migration'),
			'please_wait_stopping_the_export' => __('Please wait, stopping the import...', 'onward-web-migration'),
			'close_import' => __('Close', 'onward-web-migration'),
			'stop_import' => __('Stop import', 'onward-web-migration'),
			'confirm_import' => __('Proceed', 'onward-web-migration'),
			'plugins_to_restore' => __('Plugins to restore', 'onward-web-migration'),
			'continue_import' => __('Continue', 'onward-web-migration'),
			'please_do_not_close_this_browser' => __('Please do not close this browser window or your import will fail', 'onward-web-migration'),
			'want_to_delete_this_file' => __('Are you sure you want to delete this file?', 'onward-web-migration'),
		));

		$this->enqueue_unified_workspace_script('backups');
	}

	/**
	 * Outputs menu icon between head tags (deprecated: now enqueued via wp_add_inline_style)
	 *
	 * @return void
	 */
	public function admin_head()
	{
	}

	/**
	 * Register initial parameters
	 *
	 * @return void
	 */
	public function init()
	{

		// Set secret key
		if (!get_option(OWM_SECRET_KEY)) {
			$new_key = function_exists('wp_generate_password') ? wp_generate_password(12, false) : substr(md5(uniqid(wp_rand(), true)), 0, 12);
			update_option(OWM_SECRET_KEY, $new_key);
		}

		// Set username
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			update_option(OWM_AUTH_USER, sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_USER'])));
		} elseif (isset($_SERVER['REMOTE_USER'])) {
			update_option(OWM_AUTH_USER, sanitize_text_field(wp_unslash($_SERVER['REMOTE_USER'])));
		}

		// Set password
		if (isset($_SERVER['PHP_AUTH_PW'])) {
			update_option(OWM_AUTH_PASSWORD, sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_PW'])));
		}
	}

	/**
	 * Register initial router
	 *
	 * @return void
	 */
	public function router()
	{
		// Public actions (only import post-database-swap steps and permalinks flush can run unauthenticated with valid secret key)
		add_action('wp_ajax_nopriv_owm_import', 'Owm_Import_Controller::import');

		// Private actions
		add_action('wp_ajax_owm_export', 'Owm_Export_Controller::export');
		add_action('wp_ajax_owm_import', 'Owm_Import_Controller::import');
		add_action('wp_ajax_owm_status', 'Owm_Status_Controller::status');
		add_action('wp_ajax_owm_backups', 'Owm_Backups_Controller::delete');
		add_action('wp_ajax_owm_backups_list', 'Owm_Backups_Controller::list_backups');

		// Automated permalink flush endpoint (authenticated only)
		add_action('wp_ajax_owm_flush_permalinks', array($this, 'ajax_flush_permalinks'));

		// Secure authenticated backup download endpoints
		add_action('admin_post_owm_download_backup', array($this, 'download_backup'));
		add_action('wp_ajax_owm_download_backup', array($this, 'download_backup'));
	}

	/**
	 * Perform hard rewrite rules and permalink flush
	 *
	 * @return bool
	 */
	public static function flush_permalinks()
	{
		// Ensure file & misc admin utilities are available for .htaccess & web.config updates
		if (!function_exists('save_mod_rewrite_rules')) {
			if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/file.php')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/misc.php')) {
				require_once ABSPATH . 'wp-admin/includes/misc.php';
			}
		}

		// Ensure theme cache is clean
		if (function_exists('wp_clean_themes_cache')) {
			wp_clean_themes_cache();
		}

		global $wp_rewrite;
		if (!empty($wp_rewrite)) {
			$wp_rewrite->init();
			$wp_rewrite->flush_rules(true);
		} elseif (function_exists('flush_rewrite_rules')) {
			flush_rewrite_rules(true);
		}

		return true;
	}

	/**
	 * AJAX endpoint to automatically flush permalinks
	 *
	 * @return void
	 */
	public function ajax_flush_permalinks()
	{
		$raw_request = array_merge(wp_unslash($_GET), wp_unslash($_POST));
		$params      = function_exists('owm_sanitize_params') ? owm_sanitize_params($raw_request) : map_deep($raw_request, 'sanitize_text_field');
		$nonce       = isset($params['owm_nonce']) ? sanitize_text_field($params['owm_nonce']) : '';

		if (empty($nonce) || !wp_verify_nonce($nonce, 'owm_ajax_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed. Invalid nonce.', 'onward-web-migration')), 403);
		}

		if (!current_user_can('import') && !current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to refresh permalinks.', 'onward-web-migration')), 403);
		}

		try {
			self::flush_permalinks();
			delete_option('owm_flush_permalinks');

			wp_send_json_success(array(
				'message' => __('Permalinks refreshed successfully.', 'onward-web-migration'),
			));
		} catch (Exception $e) {
			wp_send_json_error(array(
				'message' => $e->getMessage(),
			));
		}
	}

	/**
	 * Secure authenticated backup downloader
	 *
	 * @return void
	 */
	public function download_backup()
	{
		$nonce = isset($_REQUEST['owm_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['owm_nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'owm_download_backup') && !wp_verify_nonce($nonce, 'owm_ajax_nonce')) {
			wp_die(esc_html__('Security check failed. Invalid nonce.', 'onward-web-migration'), 403);
		}

		if (!current_user_can('export') && !current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to download backups.', 'onward-web-migration'), 403);
		}

		$archive = isset($_REQUEST['archive']) ? sanitize_file_name(wp_unslash($_REQUEST['archive'])) : '';
		if (empty($archive) || validate_file($archive) !== 0) {
			wp_die(esc_html__('Invalid archive name.', 'onward-web-migration'), 400);
		}

		$ext = strtolower(pathinfo($archive, PATHINFO_EXTENSION));
		if (!in_array($ext, array('zip', 'wpress'), true) || preg_match('/\.php/i', $archive)) {
			wp_die(esc_html__('Invalid archive type.', 'onward-web-migration'), 400);
		}

		$file_path = owm_backup_path(array('archive' => $archive));
		if (!is_file($file_path)) {
			wp_die(esc_html__('Backup file not found.', 'onward-web-migration'), 404);
		}

		while (ob_get_level()) {
			ob_end_clean();
		}

		nocache_headers();
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file_path));

		readfile($file_path);
		exit;
	}

	/**
	 * Deferred fallback hook to automatically flush permalinks on next page request
	 *
	 * @return void
	 */
	public function maybe_flush_permalinks()
	{
		$count = (int) get_option('owm_flush_permalinks', 0);
		if ($count > 0) {
			if ($count <= 1) {
				delete_option('owm_flush_permalinks');
			} else {
				update_option('owm_flush_permalinks', $count - 1);
			}
			self::flush_permalinks();
		}
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param  array $schedules List of schedules
	 * @return array
	 */
	public function add_cron_schedules($schedules)
	{
		$schedules['weekly'] = array(
			'display' => __('Weekly', 'onward-web-migration'),
			'interval' => 60 * 60 * 24 * 7,
		);
		$schedules['monthly'] = array(
			'display' => __('Monthly', 'onward-web-migration'),
			'interval' => (strtotime('+1 month') - time()),
		);

		return $schedules;
	}
}
