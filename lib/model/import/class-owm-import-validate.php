<?php
/**
 * Validate Import
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Validate
{

	public static function execute($params)
	{

		// Verify file if size > 2GB and PHP = 32-bit
		if (!owm_is_filesize_supported(owm_archive_path($params))) {
			throw new Owm_Import_Exception(
				wp_kses_post(
					__( 'Your PHP is 32-bit. In order to import your file, please change your PHP version to 64-bit and try again. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration' )
				)
			);
		}

		// Set archive bytes offset
		if (isset($params['archive_bytes_offset'])) {
			$archive_bytes_offset = (int) $params['archive_bytes_offset'];
		} else {
			$archive_bytes_offset = 0;
		}

		// Set file bytes offset
		if (isset($params['file_bytes_offset'])) {
			$file_bytes_offset = (int) $params['file_bytes_offset'];
		} else {
			$file_bytes_offset = 0;
		}

		// Get total archive size
		if (isset($params['total_archive_size'])) {
			$total_archive_size = (int) $params['total_archive_size'];
		} else {
			$total_archive_size = owm_archive_bytes($params);
		}

		// What percent of archive have we processed?
		$progress = (int) min(($archive_bytes_offset / $total_archive_size) * 100, 100);

		// Set progress
		/* translators: %d: progress percentage */
		Owm_Status::info(sprintf(__('Unpacking archive...<br />%d%% complete', 'onward-web-migration'), $progress));

		// Open the archive file for reading
		$archive = new Owm_Extractor(owm_archive_path($params));

		// Set the file pointer to the one that we have saved
		$archive->set_file_pointer($archive_bytes_offset);

		// Validate the archive file consistency
		if (!$archive->is_valid()) {
			throw new Owm_Import_Exception(
				wp_kses_post(
					__( 'The archive file is corrupted. Follow <a href="#" target="_blank">this article</a> to resolve the problem.', 'onward-web-migration' )
				)
			);
		}

		$allowed_size = apply_filters('owm_max_file_size', OWM_MAX_FILE_SIZE);

		// Let's check the size of the file to make sure it is less than the maximum allowed
		if (($allowed_size > 0) && ($total_archive_size > $allowed_size)) {
			throw new Owm_Import_Exception(
				wp_kses_post(
					sprintf(
						/* translators: %s: maximum upload file size limit */
						__( 'The file that you are trying to import is over the maximum upload file size limit of <strong>%s</strong>.<br />You can remove this restriction by purchasing our <a href="#" target="_blank">Unlimited Extension</a>.', 'onward-web-migration' ),
						size_format($allowed_size)
					)
				)
			);
		}

		// Flag to hold if file data has been processed
		$completed = true;

		if ($archive->has_not_reached_eof()) {
			$file_bytes_written = 0;

			// Unpack package.json, multisite.json and database.sql files
			if (($completed = $archive->extract_by_files_array(owm_storage_path($params), array(OWM_PACKAGE_NAME, OWM_MULTISITE_NAME, OWM_DATABASE_NAME), array(), $file_bytes_written, $file_bytes_offset))) {
				$file_bytes_offset = 0;
			}

			// Get archive bytes offset
			$archive_bytes_offset = $archive->get_file_pointer();
		}

		// End of the archive?
		if ($archive->has_reached_eof()) {

			// Check package.json file
			if (false === is_file(owm_package_path($params))) {
				throw new Owm_Import_Exception(
					wp_kses_post(
						__( 'Please make sure that your file was exported using <strong>Onward Web Migration</strong> plugin. <a href="#" target="_blank">Technical details</a>', 'onward-web-migration' )
					)
				);
			}

			// Set progress
			Owm_Status::info(__('Done unpacking archive.', 'onward-web-migration'));

			// Unset archive bytes offset
			unset($params['archive_bytes_offset']);

			// Unset file bytes offset
			unset($params['file_bytes_offset']);

			// Unset total archive size
			unset($params['total_archive_size']);

			// Unset completed flag
			unset($params['completed']);

		} else {

			// What percent of archive have we processed?
			$progress = (int) min(($archive_bytes_offset / $total_archive_size) * 100, 100);

			// Set progress
			/* translators: %d: progress percentage */
			Owm_Status::info(sprintf(__('Unpacking archive...<br />%d%% complete', 'onward-web-migration'), $progress));

			// Set archive bytes offset
			$params['archive_bytes_offset'] = $archive_bytes_offset;

			// Set file bytes offset
			$params['file_bytes_offset'] = $file_bytes_offset;

			// Set total archive size
			$params['total_archive_size'] = $total_archive_size;

			// Set completed flag
			$params['completed'] = $completed;
		}

		// Close the archive file
		$archive->close();

		return $params;
	}
}
