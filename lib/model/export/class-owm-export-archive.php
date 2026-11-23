<?php
/**
 * WP Sync & Backup - Export Archive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Export_Archive
{

	public static function execute($params)
	{

		// Set progress
		Owm_Status::info(__('Creating an empty archive...', 'onward-web-migration'));

		// Create empty archive file
		$archive = new Owm_Compressor(owm_archive_path($params));
		$archive->close();

		// Set progress
		Owm_Status::info(__('Done creating an empty archive.', 'onward-web-migration'));

		return $params;
	}
}
