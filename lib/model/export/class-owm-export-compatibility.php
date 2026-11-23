<?php
/**
 * WP Sync & Backup - Export Compatibility
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Export_Compatibility
{

	public static function execute($params)
	{

		// Set progress
		Owm_Status::info(__('Checking extensions compatibility...', 'onward-web-migration'));

		// Get messages
		$messages = Owm_Compatibility::get($params);

		// Set messages
		if (empty($messages)) {
			return $params;
		}

		// Enable notifications
		add_filter('owm_notification_error_toggle', '__return_true', 20);

		// Error message
		throw new Owm_Compatibility_Exception(esc_html(implode(' ', $messages)));
	}
}
