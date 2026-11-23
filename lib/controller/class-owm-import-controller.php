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

class Owm_Import_Controller
{

	public static function index()
	{
		$model = new Owm_Backups;
		Owm_Template::render(
			'import/index',
			array(
				'backups' => $model->get_files(),
				'current_view' => 'import',
			)
		);
	}

	public static function import($params = array())
	{
		global $wp_filter;

		if (function_exists('owm_setup_environment')) {
			owm_setup_environment();
		}

		// Set params
		if (empty($params)) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Nonce and capability verified in owm_verify_secret_key below.
			$raw_request = array_merge(wp_unslash($_GET), wp_unslash($_POST));
			$params = function_exists('owm_sanitize_params') ? owm_sanitize_params($raw_request) : map_deep($raw_request, 'sanitize_text_field');
		}

		// Set priority
		$priority = 10;
		if (isset($params['priority'])) {
			$priority = (int) $params['priority'];
		}
		$params['priority'] = $priority;

		if (class_exists('Owm_Handler') && method_exists('Owm_Handler', 'set_context')) {
			Owm_Handler::set_context('import', $params);
		}

		// Set secret key
		$secret_key = null;
		if (isset($params['secret_key'])) {
			$secret_key = sanitize_text_field(trim($params['secret_key']));
		}

		$nonce = isset($params['owm_nonce']) ? sanitize_text_field($params['owm_nonce']) : '';

		try {
			// Ensure that unauthorized people cannot access import action
			if (function_exists('owm_verify_secret_key')) {
				owm_verify_secret_key($secret_key, $nonce, 'import');
			}

			// Enforce that authenticated users must have import or manage_options capability (not just export)
			if (function_exists('is_user_logged_in') && is_user_logged_in()) {
				if (!current_user_can('import') && !current_user_can('manage_options')) {
					throw new Owm_Not_Valid_Secret_Key_Exception(
						wp_kses_post(__('You do not have permission to import backups on this website.', 'onward-web-migration'))
					);
				}
			}
		} catch (Owm_Not_Valid_Secret_Key_Exception $e) {
			if (class_exists('Owm_Handler')) {
				Owm_Handler::record_exception($e);
				Owm_Handler::mark_completed();
			}
			$debug = class_exists('Owm_Handler') ? Owm_Handler::get_debug_report() : array();
			status_header(403);
			echo wp_json_encode(array('errors' => array(array('message' => $e->getMessage())), 'debug' => $debug));
			exit;
		}

		// Ensure import hooks are registered
		if (!has_filter('owm_import')) {
			(new Owm_Main_Controller)->owm_commands();
		}

		// Get hook
		if (isset($wp_filter['owm_import']) && ($filters = $wp_filter['owm_import'])) {
			// WordPress 4.7 introduces new class for working with filters/actions called WP_Hook
			// which adds another level of abstraction and we need to address it.
			if (isset($filters->callbacks)) {
				$filters = $filters->callbacks;
			}

			ksort($filters);

			// Loop over filters
			while ($hooks = current($filters)) {
				if ($priority === key($filters)) {
					foreach ($hooks as $hook) {
						try {

							// Run function hook
							$params = call_user_func_array($hook['function'], array($params));

							if (class_exists('Owm_Handler') && method_exists('Owm_Handler', 'set_context')) {
								$op = !empty($params['owm_manual_restore']) ? 'restore' : 'import';
								Owm_Handler::set_context($op, $params);
							}

							// Log request
							Owm_Log::import($params);

						} catch (Owm_Import_Retry_Exception $e) {
							if (class_exists('Owm_Handler')) {
								Owm_Handler::record_exception($e);
								Owm_Handler::mark_completed();
							}
							$debug = class_exists('Owm_Handler') ? Owm_Handler::get_debug_report($params) : array();
							$is_restore = !empty($params['owm_manual_restore']);
							$error_title = $is_restore ? __('Unable to restore', 'onward-web-migration') : __('Unable to import', 'onward-web-migration');
							status_header($e->getCode());
							echo wp_json_encode(array('errors' => array(array('title' => $error_title, 'code' => $e->getCode(), 'message' => $e->getMessage())), 'debug' => $debug));
							exit;
						} catch (Throwable $e) {
							if (class_exists('Owm_Handler')) {
								Owm_Handler::record_exception($e);
								Owm_Handler::mark_completed();
							}
							$debug = class_exists('Owm_Handler') ? Owm_Handler::get_debug_report($params) : array();
							$msg = sprintf('%s in %s:%d', $e->getMessage(), basename($e->getFile()), $e->getLine());
							$is_restore = !empty($params['owm_manual_restore']);
							$error_title = $is_restore ? __('Unable to restore', 'onward-web-migration') : __('Unable to import', 'onward-web-migration');
							Owm_Status::error($error_title, $msg, $debug);
							Owm_Notification::error($error_title, $msg);
							status_header(500);
							echo wp_json_encode(array('errors' => array(array('title' => $error_title, 'message' => $msg)), 'debug' => $debug, 'params' => $params));
							exit;
						} catch (Exception $e) {
							if (class_exists('Owm_Handler')) {
								Owm_Handler::record_exception($e);
								Owm_Handler::mark_completed();
							}
							$debug = class_exists('Owm_Handler') ? Owm_Handler::get_debug_report($params) : array();
							$msg = sprintf('%s in %s:%d', $e->getMessage(), basename($e->getFile()), $e->getLine());
							$is_restore = !empty($params['owm_manual_restore']);
							$error_title = $is_restore ? __('Unable to restore', 'onward-web-migration') : __('Unable to import', 'onward-web-migration');
							Owm_Status::error($error_title, $msg, $debug);
							Owm_Notification::error($error_title, $msg);
							status_header(500);
							echo wp_json_encode(array('errors' => array(array('title' => $error_title, 'message' => $msg)), 'debug' => $debug, 'params' => $params));
							exit;
						}
					}

					// Set completed
					$completed = true;
					if (isset($params['completed'])) {
						$completed = (bool) $params['completed'];
					}

					// Do request
					if ($completed === false || ($next = next($filters)) && ($params['priority'] = key($filters))) {
						if (class_exists('Owm_Handler')) {
							Owm_Handler::mark_completed();
						}
						if (isset($params['owm_manual_import']) || isset($params['owm_manual_restore']) || (defined('DOING_AJAX') && DOING_AJAX) || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
							echo wp_json_encode($params);
							exit;
						}

						wp_remote_post(apply_filters('owm_http_import_url', admin_url('admin-ajax.php?action=owm_import')), array(
							'timeout' => apply_filters('owm_http_import_timeout', 5),
							'blocking' => apply_filters('owm_http_import_blocking', false),
							'sslverify' => apply_filters('owm_http_import_sslverify', false),
							'headers' => apply_filters('owm_http_import_headers', array()),
							'body' => apply_filters('owm_http_import_body', $params),
						));
						exit;
					}
				}

				next($filters);
			}
		}

		if (class_exists('Owm_Handler')) {
			Owm_Handler::mark_completed();
		}
	}

	public static function buttons()
	{
		return array(
			apply_filters('owm_import_file', Owm_Template::get_content('import/button-file')),
		);
	}

	public static function http_import_headers($headers = array())
	{
		if (($user = get_option(OWM_AUTH_USER)) && ($password = get_option(OWM_AUTH_PASSWORD))) {
			if (($hash = base64_encode(sprintf('%s:%s', $user, $password)))) {
				$headers['Authorization'] = sprintf('Basic %s', $hash);
			}
		}

		return $headers;
	}

	public static function max_chunk_size()
	{
		return min(
			owm_parse_size(ini_get('post_max_size'), OWM_MAX_CHUNK_SIZE),
			owm_parse_size(ini_get('upload_max_filesize'), OWM_MAX_CHUNK_SIZE),
			owm_parse_size(OWM_MAX_CHUNK_SIZE)
		);
	}
}
