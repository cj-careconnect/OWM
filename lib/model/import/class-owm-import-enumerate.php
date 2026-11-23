<?php
/**
 * Enumerate Import
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Enumerate
{

	public static function execute($params)
	{

		// Set progress
		Owm_Status::info(__('Retrieving a list of all WordPress files...', 'onward-web-migration'));

		// Open the archive file for reading
		$archive = new Owm_Extractor(owm_archive_path($params));

		// Get total files count
		$params['total_files_count'] = $archive->get_total_files_count();

		// Get total files size
		$params['total_files_size'] = $archive->get_total_files_size();

		// Close the archive file
		$archive->close();

		// Set progress
		Owm_Status::info(__('Done retrieving a list of all WordPress files.', 'onward-web-migration'));

		return $params;
	}
}
