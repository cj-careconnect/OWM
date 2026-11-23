<?php
/**
 * WP Sync & Backup - Backups Controller
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Backups_Controller
{

	public static function index()
	{
		$model = new Owm_Backups;

		Owm_Template::render(
			'backups/index',
			array(
				'backups' => $model->get_files(),
				'username' => get_option(OWM_AUTH_USER),
				'password' => get_option(OWM_AUTH_PASSWORD),
				'current_view' => 'backups',
			)
		);
	}

	public static function delete($params = array())
	{
		$errors = array();

		// 1. Verify Nonce
		$nonce = '';
		if (isset($_POST['owm_nonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_POST['owm_nonce']));
		} elseif (isset($_REQUEST['_wpnonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
		} elseif (isset($params['owm_nonce'])) {
			$nonce = sanitize_text_field($params['owm_nonce']);
		}

		if (empty($nonce) || !wp_verify_nonce($nonce, 'owm_ajax_nonce')) {
			status_header(403);
			echo wp_json_encode(array('errors' => array(__('Security check failed. Invalid nonce.', 'onward-web-migration'))));
			exit;
		}

		// 2. Verify User Capability
		if (!current_user_can('import') && !current_user_can('export') && !current_user_can('manage_options')) {
			status_header(403);
			echo wp_json_encode(array('errors' => array(__('You do not have permission to delete backups.', 'onward-web-migration'))));
			exit;
		}

		// Set params
		if (empty($params)) {
			$params = function_exists('owm_sanitize_params') ? owm_sanitize_params(wp_unslash($_POST)) : map_deep(wp_unslash($_POST), 'sanitize_text_field');
		}

		// Set archive
		$archive = null;
		if (isset($params['archive'])) {
			$archive = sanitize_file_name(basename(trim($params['archive'])));
		}

		if (empty($archive) || validate_file($archive) !== 0) {
			status_header(400);
			echo wp_json_encode(array('errors' => array(__('Invalid archive path.', 'onward-web-migration'))));
			exit;
		}

		$ext = strtolower(pathinfo($archive, PATHINFO_EXTENSION));
		if (!in_array($ext, array('zip', 'wpress'), true) || preg_match('/\.php/i', $archive)) {
			status_header(400);
			echo wp_json_encode(array('errors' => array(__('Invalid archive file type.', 'onward-web-migration'))));
			exit;
		}

		$model = new Owm_Backups;

		try {
			// Delete file
			$model->delete_file($archive);
		} catch (Exception $e) {
			$errors[] = $e->getMessage();
		}

		echo wp_json_encode(array('errors' => $errors));
		exit;
	}

	public static function list_backups($params = array())
	{
		// 1. Verify Nonce
		$nonce = '';
		if (isset($_POST['owm_nonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_POST['owm_nonce']));
		} elseif (isset($_GET['owm_nonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_GET['owm_nonce']));
		} elseif (isset($_REQUEST['_wpnonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
		} elseif (isset($params['owm_nonce'])) {
			$nonce = sanitize_text_field($params['owm_nonce']);
		}

		if (empty($nonce) || !wp_verify_nonce($nonce, 'owm_ajax_nonce')) {
			status_header(403);
			echo wp_json_encode(array('errors' => array(__('Security check failed. Invalid nonce.', 'onward-web-migration'))));
			exit;
		}

		// 2. Verify User Capability
		if (!current_user_can('export') && !current_user_can('import') && !current_user_can('manage_options')) {
			status_header(403);
			echo wp_json_encode(array('errors' => array(__('You do not have permission to view backups.', 'onward-web-migration'))));
			exit;
		}

		$model = new Owm_Backups;
		$files = $model->get_files();
		$backups = array();

		foreach ($files as $idx => $bk) {
			$mtime = isset($bk['mtime']) && $bk['mtime'] ? (int) $bk['mtime'] : time();
			$bytes = isset($bk['size']) && $bk['size'] ? (int) $bk['size'] : 0;
			if (!$bytes) {
				try {
					$filePath = owm_backup_path(array('archive' => $bk['filename']));
					if (is_file($filePath)) {
						$bytes = filesize($filePath);
					}
				} catch (Exception $e) {
				}
			}
			$size_mb = $bytes ? round($bytes / (1024 * 1024), 2) : 0;
			$backups[] = array(
				'id' => 'bk-' . ($idx + 1),
				'name' => basename($bk['filename']),
				'rawFilename' => $bk['filename'],
				/* translators: %s: relative time difference */
				'date' => function_exists('human_time_diff') ? sprintf(__('%s ago', 'onward-web-migration'), human_time_diff($mtime)) : gmdate('Y-m-d', $mtime),
				'timestamp' => gmdate('M d, Y • h:i A', $mtime),
				'sizeMB' => $size_mb,
				'type' => 'Server Snapshot',
				'downloadUrl' => function_exists('owm_backup_url') ? owm_backup_url(array('archive' => $bk['filename'])) : '#',
			);
		}

		echo wp_json_encode(array('backups' => $backups));
		exit;
	}
}
