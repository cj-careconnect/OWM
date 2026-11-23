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

class Owm_Export_Controller
{

	public static function index()
	{
		$model = new Owm_Backups;
		Owm_Template::render(
			'export/index',
			array(
				'backups' => $model->get_files(),
				'current_view' => 'all',
			)
		);
	}

	public static function export($params = array())
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
		$priority = 5;
		if (isset($params['priority'])) {
			$priority = (int) $params['priority'];
		}
		$params['priority'] = $priority;

		if (class_exists('Owm_Handler') && method_exists('Owm_Handler', 'set_context')) {
			Owm_Handler::set_context('export', $params);
		}

		// Set secret key
		$secret_key = null;
		if (isset($params['secret_key'])) {
			$secret_key = sanitize_text_field(trim($params['secret_key']));
		}

		$nonce = isset($params['owm_nonce']) ? sanitize_text_field($params['owm_nonce']) : '';

		try {
			// Ensure that unauthorized people cannot access export action
			if (function_exists('owm_verify_secret_key')) {
				owm_verify_secret_key($secret_key, $nonce, 'export');
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

		// Ensure export hooks are registered
		if (!has_filter('owm_export')) {
			(new Owm_Main_Controller)->owm_commands();
		}

		// Get hook
		if (isset($wp_filter['owm_export']) && ($filters = $wp_filter['owm_export'])) {
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
								Owm_Handler::set_context('export', $params);
							}

							// Log request
							Owm_Log::export($params);

						} catch (Throwable $e) {
							if (class_exists('Owm_Handler')) {
								Owm_Handler::record_exception($e);
								Owm_Handler::mark_completed();
							}
							$debug = class_exists('Owm_Handler') ? Owm_Handler::get_debug_report($params) : array();
							$msg = sprintf('%s in %s:%d', $e->getMessage(), basename($e->getFile()), $e->getLine());
							Owm_Status::error(__('Unable to export', 'onward-web-migration'), $msg, $debug);
							Owm_Notification::error(__('Unable to export', 'onward-web-migration'), $msg);
							if (!empty($params['storage'])) {
								try {
									Owm_Directory::delete(owm_storage_path($params));
								} catch (Throwable $del_e) {
								}
							}
							status_header(500);
							echo wp_json_encode(array('errors' => array(array('message' => $msg)), 'debug' => $debug));
							exit;
						} catch (Exception $e) {
							if (class_exists('Owm_Handler')) {
								Owm_Handler::record_exception($e);
								Owm_Handler::mark_completed();
							}
							$debug = class_exists('Owm_Handler') ? Owm_Handler::get_debug_report($params) : array();
							$msg = sprintf('%s in %s:%d', $e->getMessage(), basename($e->getFile()), $e->getLine());
							Owm_Status::error(__('Unable to export', 'onward-web-migration'), $msg, $debug);
							Owm_Notification::error(__('Unable to export', 'onward-web-migration'), $msg);
							if (!empty($params['storage'])) {
								try {
									Owm_Directory::delete(owm_storage_path($params));
								} catch (Throwable $del_e) {
								}
							}
							status_header(500);
							echo wp_json_encode(array('errors' => array(array('message' => $msg)), 'debug' => $debug));
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
						if (isset($params['owm_manual_export']) || (defined('DOING_AJAX') && DOING_AJAX) || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
							echo wp_json_encode($params);
							exit;
						}

						wp_remote_post(apply_filters('owm_http_export_url', admin_url('admin-ajax.php?action=owm_export')), array(
							'timeout' => apply_filters('owm_http_export_timeout', 5),
							'blocking' => apply_filters('owm_http_export_blocking', false),
							'sslverify' => apply_filters('owm_http_export_sslverify', false),
							'headers' => apply_filters('owm_http_export_headers', array()),
							'body' => apply_filters('owm_http_export_body', $params),
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
			apply_filters('owm_export_file', Owm_Template::get_content('export/button-file')),
		);
	}

	public static function http_export_headers($headers = array())
	{
		if (($user = get_option(OWM_AUTH_USER)) && ($password = get_option(OWM_AUTH_PASSWORD))) {
			if (($hash = base64_encode(sprintf('%s:%s', $user, $password)))) {
				$headers['Authorization'] = sprintf('Basic %s', $hash);
			}
		}

		return $headers;
	}
}
