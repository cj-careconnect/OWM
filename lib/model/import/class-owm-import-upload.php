<?php
/**
 * WP Sync & Backup - Import Upload
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Upload
{

	private static function validate($params)
	{
		// 1. Verify Nonce
		$nonce = isset($params['owm_nonce']) ? sanitize_text_field($params['owm_nonce']) : '';
		if (empty($nonce)) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if (isset($_POST['owm_nonce'])) {
				$nonce = sanitize_text_field(wp_unslash($_POST['owm_nonce']));
			} elseif (isset($_REQUEST['_wpnonce'])) {
				$nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		if (empty($nonce) || !wp_verify_nonce($nonce, 'owm_ajax_nonce')) {
			throw new Owm_Import_Retry_Exception(
				esc_html__('Security check failed. Invalid nonce.', 'onward-web-migration'),
				403
			);
		}

		// 2. Verify User Capability
		if (!current_user_can('import') && !current_user_can('manage_options')) {
			throw new Owm_Import_Retry_Exception(
				esc_html__('You do not have permission to upload import archives.', 'onward-web-migration'),
				403
			);
		}

		// 3. Strictly Validate Archive Name & File Extension
		$archive_name = isset($params['archive']) ? sanitize_file_name($params['archive']) : '';
		if (empty($archive_name) || validate_file($archive_name) !== 0) {
			throw new Owm_Import_Retry_Exception(
				esc_html__('Invalid archive filename.', 'onward-web-migration'),
				400
			);
		}

		$ext = strtolower(pathinfo($archive_name, PATHINFO_EXTENSION));
		if (!in_array($ext, array('zip', 'wpress'), true) || preg_match('/\.php/i', $archive_name)) {
			throw new Owm_Import_Retry_Exception(
				esc_html__('Invalid archive file type. Only .zip archives are allowed.', 'onward-web-migration'),
				400
			);
		}

		// 4. Validate Uploaded File Payload
		if (!array_key_exists('upload-file', $_FILES) || !is_array($_FILES['upload-file'])) {
			throw new Owm_Import_Retry_Exception(
				esc_html__('Missing upload file.', 'onward-web-migration'),
				400
			);
		}

		if (!array_key_exists('error', $_FILES['upload-file'])) {
			throw new Owm_Import_Retry_Exception(
				esc_html__('Missing error key in upload file.', 'onward-web-migration'),
				400
			);
		}

		if (!array_key_exists('tmp_name', $_FILES['upload-file'])) {
			throw new Owm_Import_Retry_Exception(
				esc_html__('Missing tmp_name in upload file.', 'onward-web-migration'),
				400
			);
		}
	}

	public static function execute($params)
	{
		self::validate($params);

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$error = isset($_FILES['upload-file']['error']) ? (int) $_FILES['upload-file']['error'] : UPLOAD_ERR_NO_FILE;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$upload = isset($_FILES['upload-file']['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES['upload-file']['tmp_name'])) : '';
		$archive = owm_archive_path($params);

		switch ($error) {
			case UPLOAD_ERR_OK:
				try {
					owm_copy($upload, $archive);
					owm_unlink($upload);
				} catch (Exception $e) {
					throw new Owm_Import_Retry_Exception(
						esc_html(
							sprintf(
								/* translators: %s: error message */
								__('Unable to upload the file because %s', 'onward-web-migration'),
								$e->getMessage()
							)
						),
						400
					);
				}
				break;
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
			case UPLOAD_ERR_PARTIAL:
			case UPLOAD_ERR_NO_FILE:
				// File is too large, reduce the size and try again
				throw new Owm_Import_Retry_Exception(
					esc_html__('The file is too large, retrying with smaller size.', 'onward-web-migration'),
					413
				);
			case UPLOAD_ERR_NO_TMP_DIR:
				throw new Owm_Import_Retry_Exception(
					esc_html__('Missing a temporary folder.', 'onward-web-migration'),
					400
				);
			case UPLOAD_ERR_CANT_WRITE:
				throw new Owm_Import_Retry_Exception(
					esc_html__('Failed to write file to disk.', 'onward-web-migration'),
					400
				);
			case UPLOAD_ERR_EXTENSION:
				throw new Owm_Import_Retry_Exception(
					esc_html__('A PHP extension stopped the file upload.', 'onward-web-migration'),
					400
				);
			default:
				throw new Owm_Import_Retry_Exception(
					esc_html(
						sprintf(
							/* translators: %s: error code */
							__('Unrecognized error %s during upload.', 'onward-web-migration'),
							$error
						)
					),
					400
				);
		}

		if (class_exists('Owm_Handler')) {
			Owm_Handler::mark_completed();
		}
		echo wp_json_encode(array('errors' => array()));
		exit;
	}
}
