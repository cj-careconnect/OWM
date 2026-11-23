<?php
/**
 * Error and Shutdown Handler
 *
 * @package Onward_Web_Migration
 */

if (!defined('ABSPATH')) {
	exit;
}

class Owm_Handler
{

	/**
	 * Collected PHP errors during the current request
	 *
	 * @var array
	 */
	protected static $errors = array();

	/**
	 * Context for the current operation ('export' or 'import') and params
	 *
	 * @var array
	 */
	protected static $context = array(
		'operation' => 'export',
		'priority' => 0,
		'params' => array(),
	);

	/**
	 * Whether the request finished cleanly without an uncaught fatal shutdown
	 *
	 * @var boolean
	 */
	protected static $completed_cleanly = false;

	/**
	 * Set operation context for diagnostics and fatal recovery
	 *
	 * @param  string $operation 'export' or 'import'
	 * @param  array  $params    Request parameters
	 * @return void
	 */
	public static function set_context($operation, $params = array())
	{
		self::$context = array(
			'operation' => $operation,
			'priority' => isset($params['priority']) ? (int) $params['priority'] : 0,
			'params' => is_array($params) ? $params : array(),
		);
	}

	/**
	 * Mark current request as completed cleanly so shutdown handler does not override output
	 *
	 * @return void
	 */
	public static function mark_completed()
	{
		self::$completed_cleanly = true;
	}

	/**
	 * Map PHP error constant number to human-readable level name
	 *
	 * @param  integer $errno Error number
	 * @return string
	 */
	public static function error_type_name($errno)
	{
		$map = array(
			E_ERROR => 'E_ERROR (Fatal Error)',
			E_WARNING => 'E_WARNING',
			E_PARSE => 'E_PARSE (Parse Error)',
			E_NOTICE => 'E_NOTICE',
			E_CORE_ERROR => 'E_CORE_ERROR',
			E_CORE_WARNING => 'E_CORE_WARNING',
			E_COMPILE_ERROR => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING => 'E_COMPILE_WARNING',
			E_USER_ERROR => 'E_USER_ERROR',
			E_USER_WARNING => 'E_USER_WARNING',
			E_USER_NOTICE => 'E_USER_NOTICE',
			2048 => 'E_STRICT',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED => 'E_DEPRECATED',
			E_USER_DEPRECATED => 'E_USER_DEPRECATED',
		);

		return isset($map[$errno]) ? $map[$errno] : 'PHP_ERROR (' . (int) $errno . ')';
	}

	/**
	 * Map operation priority to human-readable step name
	 *
	 * @param  string  $operation 'export' or 'import'
	 * @param  integer $priority  Step priority
	 * @return string
	 */
	public static function step_label($operation, $priority)
	{
		$priority = (int) $priority;
		if ($operation === 'import' || $operation === 'restore') {
			$steps = array(
				5 => 'Upload Archive Chunk (priority 5)',
				10 => 'Compatibility Check (priority 10)',
				50 => 'Validate & Unpack Config/Database (priority 50)',
				100 => 'Confirm Overwrite (priority 100)',
				150 => 'Prepare Blogs (priority 150)',
				200 => 'Enumerate Archive Files (priority 200)',
				250 => 'Extract Content Files (priority 250)',
				270 => 'Activate Must-Use Plugins (priority 270)',
				300 => 'Restore Database SQL (priority 300)',
				350 => 'Finalize Import & Customizer (priority 350)',
				400 => 'Clean Temporary Storage (priority 400)',
			);
		} else {
			$steps = array(
				5 => 'Initialize & Compatibility Check (priority 5)',
				10 => 'Create Empty Archive (priority 10)',
				50 => 'Prepare Configuration package.json (priority 50)',
				60 => 'Archive Configuration File (priority 60)',
				100 => 'Enumerate WordPress Files (priority 100)',
				150 => 'Archive Content Files (priority 150)',
				200 => 'Export Database Tables to SQL (priority 200)',
				220 => 'Archive Database SQL File (priority 220)',
				250 => 'Finalize & Move Archive to Backups (priority 250)',
				300 => 'Clean Temporary Storage (priority 300)',
			);
		}

		return isset($steps[$priority]) ? $steps[$priority] : sprintf('%s Step (priority %d)', ucfirst($operation), $priority);
	}

	/**
	 * Error handler
	 *
	 * @param  integer $errno   Error level
	 * @param  string  $errstr  Error message
	 * @param  string  $errfile Error file
	 * @param  integer $errline Error line
	 * @return boolean
	 */
	public static function error($errno, $errstr, $errfile, $errline)
	{
		$entry = array(
			'type' => self::error_type_name($errno),
			'errno' => (int) $errno,
			'message' => (string) $errstr,
			'file' => (string) $errfile,
			'line' => (int) $errline,
			'timestamp' => gmdate('Y-m-d H:i:s'),
		);

		// Keep up to 50 unique error entries per request
		if (count(self::$errors) < 50) {
			$key = md5($errno . '|' . $errstr . '|' . $errfile . '|' . $errline);
			self::$errors[$key] = $entry;
		}

		try {
			Owm_Log::error(array(
				'Number' => $errno,
				'Type' => $entry['type'],
				'Message' => $errstr,
				'File' => $errfile,
				'Line' => $errline,
			));
		} catch (Exception $e) {
		} catch (Throwable $e) {
		}

		// Return false for fatal-class errors so PHP handles them if needed, true for warnings/notices
		return !in_array($errno, array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true);
	}

	/**
	 * Record an exception or Throwable into the error list
	 *
	 * @param  object $exception Exception or Throwable instance
	 * @return void
	 */
	public static function record_exception($exception)
	{
		if (!is_object($exception)) {
			return;
		}

		$entry = array(
			'type' => get_class($exception),
			'errno' => (int) $exception->getCode(),
			'message' => $exception->getMessage(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
			'timestamp' => gmdate('Y-m-d H:i:s'),
		);

		$key = md5($entry['type'] . '|' . $entry['message'] . '|' . $entry['file'] . '|' . $entry['line']);
		self::$errors[$key] = $entry;

		try {
			Owm_Log::error($entry);
		} catch (Exception $e) {
		} catch (Throwable $e) {
		}
	}

	/**
	 * Get all PHP errors captured in memory + recent entries from storage/error.log
	 *
	 * @return array
	 */
	public static function get_errors()
	{
		$list = array_values(self::$errors);

		// Also include last error from error_get_last() if not already present
		if (($last = error_get_last()) && is_array($last)) {
			$key = md5($last['type'] . '|' . $last['message'] . '|' . $last['file'] . '|' . $last['line']);
			if (!isset(self::$errors[$key])) {
				array_unshift($list, array(
					'type' => self::error_type_name($last['type']),
					'errno' => (int) $last['type'],
					'message' => (string) $last['message'],
					'file' => (string) $last['file'],
					'line' => (int) $last['line'],
					'timestamp' => gmdate('Y-m-d H:i:s'),
				));
			}
		}

		// Also read last 15 entries from OWM error.log if available
		try {
			if (function_exists('owm_error_path')) {
				$err_file = owm_error_path();
				if (is_file($err_file) && is_readable($err_file)) {
					global $wp_filesystem;
					if (empty($wp_filesystem)) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
						WP_Filesystem();
					}
					$raw = '';
					if ($wp_filesystem) {
						$raw = (string) $wp_filesystem->get_contents($err_file);
						if (strlen($raw) > 16384) {
							$raw = substr($raw, -16384);
						}
					}

					$lines = array_filter(array_map('trim', explode("\n", $raw)));
					$recent_log = array();
					$current_date = '';
					foreach ($lines as $line) {
						if (substr($line, 0, 1) === '{') {
							$decoded = json_decode($line, true);
							if (is_array($decoded)) {
								$msg = isset($decoded['Message']) ? $decoded['Message'] : (isset($decoded['message']) ? $decoded['message'] : '');
								$file = isset($decoded['File']) ? $decoded['File'] : (isset($decoded['file']) ? $decoded['file'] : '');
								$ln = isset($decoded['Line']) ? $decoded['Line'] : (isset($decoded['line']) ? $decoded['line'] : 0);
								$num = isset($decoded['Number']) ? $decoded['Number'] : (isset($decoded['type']) ? $decoded['type'] : 0);
								$recent_log[] = array(
									'type' => is_numeric($num) ? self::error_type_name((int) $num) : (string) $num,
									'message' => (string) $msg,
									'file' => (string) $file,
									'line' => (int) $ln,
									'timestamp' => $current_date ? $current_date : gmdate('Y-m-d H:i:s'),
									'from_log' => true,
								);
							}
						} else {
							$current_date = $line;
						}
					}

					if (!empty($recent_log)) {
						$recent_log = array_slice(array_reverse($recent_log), 0, 12);
						foreach ($recent_log as $log_item) {
							if (count($list) >= 25) {
								break;
							}
							$exists = false;
							foreach ($list as $existing) {
								if ($existing['message'] === $log_item['message'] && $existing['file'] === $log_item['file'] && (int) $existing['line'] === (int) $log_item['line']) {
									$exists = true;
									break;
								}
							}
							if (!$exists && !empty($log_item['message'])) {
								$list[] = $log_item;
							}
						}
					}
				}
			}
		} catch (Exception $e) {
		} catch (Throwable $e) {
		}

		return $list;
	}

	/**
	 * Gather comprehensive server stats, PHP environment, MySQL, disk, and operation state for debugging
	 *
	 * @param  array $extra_params Optional request parameters
	 * @return array
	 */
	public static function get_debug_report($extra_params = array())
	{
		global $wpdb, $wp_version;

		$params = !empty($extra_params) ? $extra_params : self::$context['params'];
		$operation = isset(self::$context['operation']) ? self::$context['operation'] : 'export';
		$priority = isset($params['priority']) ? (int) $params['priority'] : (int) self::$context['priority'];

		$mem_usage = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
		$mem_peak = function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : 0;
		$mem_limit = (string) @ini_get('memory_limit');

		$disk_free_backups = @disk_free_space(defined('OWM_BACKUPS_PATH') && is_dir(OWM_BACKUPS_PATH) ? OWM_BACKUPS_PATH : WP_CONTENT_DIR);
		$disk_total_backups = @disk_total_space(defined('OWM_BACKUPS_PATH') && is_dir(OWM_BACKUPS_PATH) ? OWM_BACKUPS_PATH : WP_CONTENT_DIR);

		$mysql_version = 'Unknown';
		$mysql_driver = 'Unknown';
		$last_db_error = '';
		if (isset($wpdb) && is_object($wpdb)) {
			$mysql_driver = !empty($wpdb->use_mysqli) ? 'mysqli' : 'mysql';
			$last_db_error = !empty($wpdb->last_error) ? $wpdb->last_error : '';
			if (method_exists($wpdb, 'db_version')) {
				$mysql_version = $wpdb->db_version();
			}
		}

		$profile = array();
		if (class_exists('Owm_Resource') && method_exists('Owm_Resource', 'profile')) {
			try {
				$profile = Owm_Resource::profile();
			} catch (Exception $e) {
			} catch (Throwable $e) {
			}
		}

		$storage_dir = defined('OWM_STORAGE_PATH') ? OWM_STORAGE_PATH : '';
		$backups_dir = defined('OWM_BACKUPS_PATH') ? OWM_BACKUPS_PATH : '';

		// Sanitize request params (remove secret_key and owm_nonce)
		$safe_params = is_array($params) ? (function_exists('owm_sanitize_params') ? owm_sanitize_params($params) : map_deep($params, 'sanitize_text_field')) : array();
		if (isset($safe_params['secret_key'])) {
			$safe_params['secret_key'] = '***REDACTED***';
		}
		if (isset($safe_params['owm_nonce'])) {
			$safe_params['owm_nonce'] = '***REDACTED***';
		}

		return array(
			'timestamp' => gmdate('Y-m-d H:i:s T'),
			'operation' => array(
				'type' => ucfirst($operation),
				'priority' => $priority,
				'step' => self::step_label($operation, $priority),
				'storage_id' => isset($safe_params['storage']) ? sanitize_text_field($safe_params['storage']) : '',
				'archive' => isset($safe_params['archive']) ? sanitize_text_field($safe_params['archive']) : '',
				'table_index' => isset($safe_params['table_index']) ? (int) $safe_params['table_index'] : null,
				'table_offset' => isset($safe_params['table_offset']) ? (int) $safe_params['table_offset'] : null,
				'total_tables' => isset($safe_params['total_tables_count']) ? (int) $safe_params['total_tables_count'] : null,
				'processed_bytes' => isset($safe_params['processed_files_size']) ? (int) $safe_params['processed_files_size'] : null,
				'total_files_bytes' => isset($safe_params['total_files_size']) ? (int) $safe_params['total_files_size'] : null,
				'total_files_count' => isset($safe_params['total_files_count']) ? (int) $safe_params['total_files_count'] : null,
				'params' => $safe_params,
			),
			'server_stats' => array(
				'php_version' => PHP_VERSION . ' (' . (PHP_INT_SIZE === 8 ? '64-bit' : '32-bit') . ')',
				'php_sapi' => function_exists('php_sapi_name') ? php_sapi_name() : 'unknown',
				'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
				'os' => PHP_OS,
				'wp_version' => isset($wp_version) ? $wp_version : 'Unknown',
				'plugin_version' => defined('OWM_VERSION') ? OWM_VERSION : '1.0.6',
				'mysql_version' => $mysql_version . ' (' . $mysql_driver . ')',
				'last_db_error' => $last_db_error,
				'memory_limit' => $mem_limit,
				'memory_usage' => self::format_bytes($mem_usage) . ' (' . $mem_usage . ' B)',
				'memory_peak' => self::format_bytes($mem_peak) . ' (' . $mem_peak . ' B)',
				'max_execution_time' => (string) @ini_get('max_execution_time') . 's',
				'max_input_time' => (string) @ini_get('max_input_time') . 's',
				'upload_max_filesize' => (string) @ini_get('upload_max_filesize'),
				'post_max_size' => (string) @ini_get('post_max_size'),
				'Resource_Profile' => isset($profile['name']) ? sprintf('%s (%s MB/s deflate)', $profile['name'], isset($profile['mbps']) ? $profile['mbps'] : '0') : 'N/A',
				'disk_free_space' => $disk_free_backups !== false ? self::format_bytes($disk_free_backups) : 'Unknown',
				'disk_total_space' => $disk_total_backups !== false ? self::format_bytes($disk_total_backups) : 'Unknown',
				'storage_writable' => ($storage_dir && is_dir($storage_dir) && owm_is_writable($storage_dir)) ? 'Yes (' . $storage_dir . ')' : 'No (' . $storage_dir . ')',
				'backups_writable' => ($backups_dir && is_dir($backups_dir) && owm_is_writable($backups_dir)) ? 'Yes (' . $backups_dir . ')' : 'No (' . $backups_dir . ')',
				'extensions' => array(
					'zlib' => extension_loaded('zlib'),
					'zip' => extension_loaded('zip'),
					'mysqli' => extension_loaded('mysqli'),
					'pdo_mysql' => extension_loaded('pdo_mysql'),
					'curl' => extension_loaded('curl'),
					'mbstring' => extension_loaded('mbstring'),
					'openssl' => extension_loaded('openssl'),
				),
			),
			'php_errors' => self::get_errors(),
		);
	}

	/**
	 * Format bytes into human-readable size
	 *
	 * @param  numeric $bytes Bytes
	 * @return string
	 */
	public static function format_bytes($bytes)
	{
		$bytes = (float) $bytes;
		if ($bytes <= 0) {
			return '0 B';
		}
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$i = floor(log($bytes, 1024));
		$i = min($i, count($units) - 1);
		return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
	}

	/**
	 * Shutdown handler — intercepts fatal PHP errors before WordPress outputs its generic HTML death screen
	 *
	 * @return void
	 */
	public static function shutdown()
	{
		$error = error_get_last();
		if ($error) {
			try {
				Owm_Log::error($error);
			} catch (Exception $e) {
			} catch (Throwable $e) {
			}
		}

		if (self::$completed_cleanly || !$error) {
			return;
		}

		$fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR);
		if (!in_array((int) $error['type'], $fatal_types, true)) {
			return;
		}

		$type_name = self::error_type_name($error['type']);
		$detail_msg = sprintf(
			'%s: %s in %s on line %d',
			$type_name,
			$error['message'],
			$error['file'],
			(int) $error['line']
		);

		$debug = self::get_debug_report();
		$op = isset(self::$context['operation']) ? self::$context['operation'] : 'export';
		if ($op === 'restore') {
			$title = __('Unable to restore (Fatal PHP Error)', 'onward-web-migration');
		} elseif ($op === 'import') {
			$title = __('Unable to import (Fatal PHP Error)', 'onward-web-migration');
		} else {
			$title = __('Unable to export (Fatal PHP Error)', 'onward-web-migration');
		}

		try {
			Owm_Status::error($title, $detail_msg, $debug);
		} catch (Exception $e) {
		} catch (Throwable $e) {
		}

		// Clear any buffered WordPress "There has been a critical error on this website" output
		while (ob_get_level() > 0) {
			@ob_end_clean();
		}

		if (!headers_sent()) {
			@status_header(500);
			@header('Content-Type: application/json; charset=utf-8');
		}

		echo wp_json_encode(array(
			'errors' => array(
				array(
					'message' => $detail_msg,
					'title' => $title,
				),
			),
			'debug' => $debug,
		));
	}
}
