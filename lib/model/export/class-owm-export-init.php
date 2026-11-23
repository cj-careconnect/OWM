<?php
/**
 * WP Sync & Backup - Export Init
 */

if (!defined('ABSPATH')) {
	exit;
}

class Owm_Export_Init
{

	public static function execute($params)
	{
		// Reset status to info
		Owm_Status::info(__('Initializing export...', 'onward-web-migration'));

		$blog_id = null;

		// Get subsite Blog ID
		if (isset($params['options']['sites']) && ($sites = $params['options']['sites'])) {
			if (count($sites) === 1) {
				$blog_id = array_shift($sites);
			}
		}

		// Set archive
		if (empty($params['archive'])) {
			$params['archive'] = owm_archive_file($blog_id);
		}

		// Set storage
		if (empty($params['storage'])) {
			$params['storage'] = owm_storage_folder();
		}

		return $params;
	}
}
