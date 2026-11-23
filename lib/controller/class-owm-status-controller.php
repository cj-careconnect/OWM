<?php
/**
 * Status Controller
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Status_Controller
{

	public static function status($params = array())
	{
		// 1. Validate Nonce
		$nonce = '';
		if (isset($_GET['owm_nonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_GET['owm_nonce']));
		} elseif (isset($_POST['owm_nonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_POST['owm_nonce']));
		} elseif (isset($_REQUEST['_wpnonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
		} elseif (isset($params['owm_nonce'])) {
			$nonce = sanitize_text_field($params['owm_nonce']);
		}

		$is_valid_nonce = !empty($nonce) && wp_verify_nonce($nonce, 'owm_ajax_nonce');

		if (!$is_valid_nonce) {
			// Secret-key fallback is ONLY permitted during active unauthenticated post-db-restore steps (priority >= 300)
			$expected   = get_option(OWM_SECRET_KEY);
			$secret_key = isset($_GET['secret_key']) ? sanitize_text_field(wp_unslash($_GET['secret_key'])) : (isset($_POST['secret_key']) ? sanitize_text_field(wp_unslash($_POST['secret_key'])) : '');
			$priority   = isset($_GET['priority']) ? (int) $_GET['priority'] : (isset($_POST['priority']) ? (int) $_POST['priority'] : 0);

			$is_valid_key = !empty($expected) && !empty($secret_key) && (
				function_exists('hash_equals')
					? hash_equals((string) $expected, (string) $secret_key)
					: ((string) $secret_key === (string) $expected)
			);

			if (!$is_valid_key || $priority < 300) {
				status_header(403);
				echo wp_json_encode(array('errors' => array(array('message' => __('Security check failed. Invalid nonce.', 'onward-web-migration')))));
				exit;
			}
		}

		// 2. Validate Capability for Logged-In Users
		if (function_exists('is_user_logged_in') && is_user_logged_in()) {
			if (!current_user_can('export') && !current_user_can('import') && !current_user_can('manage_options')) {
				status_header(403);
				echo wp_json_encode(array('errors' => array(array('message' => __('You do not have permission to view migration status.', 'onward-web-migration')))));
				exit;
			}
		}

		// Set params
		if (empty($params)) {
			$params = function_exists('owm_sanitize_params') ? owm_sanitize_params(wp_unslash($_GET)) : map_deep(wp_unslash($_GET), 'sanitize_text_field');
		}

		$status = get_option(OWM_STATUS, array());

		$safe_debug_params = array(
			'priority' => isset($params['priority']) ? absint($params['priority']) : 0,
			'storage'  => isset($params['storage']) ? sanitize_file_name($params['storage']) : '',
			'archive'  => isset($params['archive']) ? sanitize_file_name($params['archive']) : '',
		);

		if (isset($status['type']) && $status['type'] === 'error' && empty($status['debug'])) {
			if (class_exists('Owm_Handler') && method_exists('Owm_Handler', 'get_debug_report')) {
				$status['debug'] = Owm_Handler::get_debug_report($safe_debug_params);
			}
		}

		if (isset($params['include_debug']) && class_exists('Owm_Handler') && method_exists('Owm_Handler', 'get_debug_report')) {
			$status['debug'] = Owm_Handler::get_debug_report($safe_debug_params);
		}

		echo wp_json_encode($status);
		exit;
	}
}
