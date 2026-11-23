<?php
/**
 * Import Content
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Content
{

	public static function execute($params)
	{
		// Raise PHP execution time and memory limits for large file extractions
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for long-running file extraction operations.
		@set_time_limit(0);
		if (function_exists('wp_raise_memory_limit')) {
			@wp_raise_memory_limit('admin');
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

		// Get processed files size
		if (isset($params['processed_files_size'])) {
			$processed_files_size = (int) $params['processed_files_size'];
		} else {
			$processed_files_size = 0;
		}

		// Get total files size
		if (isset($params['total_files_size'])) {
			$total_files_size = (int) $params['total_files_size'];
		} else {
			$total_files_size = 1;
		}

		// Get total files count
		if (isset($params['total_files_count'])) {
			$total_files_count = (int) $params['total_files_count'];
		} else {
			$total_files_count = 1;
		}

		// Read blogs.json file
		$handle = owm_open(owm_blogs_path($params), 'r');

		// Parse blogs.json file
		$blogs = owm_read($handle, filesize(owm_blogs_path($params)));
		$blogs = json_decode($blogs, true);

		// Close handle
		owm_close($handle);

		// What percent of files have we processed?
		$progress = (int) min(($processed_files_size / $total_files_size) * 100, 100);

		// Set progress
		/* translators: 1: total files count, 2: progress percentage */
		Owm_Status::info(sprintf(__('Restoring %1$d files...<br />%2$d%% complete', 'onward-web-migration'), $total_files_count, $progress));

		// Flag to hold if file data has been processed
		$completed = true;

		// Start time
		$start = microtime(true);

		// Open the archive file for reading
		$archive = new Owm_Extractor(owm_archive_path($params));

		// Set the file pointer to the one that we have saved
		$archive->set_file_pointer($archive_bytes_offset);

		$old_paths = array();
		$new_paths = array();

		// Set extract paths
		foreach ($blogs as $blog) {
			if (owm_main_site($blog['Old']['BlogID']) === false) {
				if (defined('UPLOADBLOGSDIR')) {
					// Old sites dir style
					$old_paths[] = owm_files_path($blog['Old']['BlogID']);
					$new_paths[] = owm_files_path($blog['New']['BlogID']);

					// New sites dir style
					$old_paths[] = owm_sites_path($blog['Old']['BlogID']);
					$new_paths[] = owm_files_path($blog['New']['BlogID']);
				} else {
					// Old sites dir style
					$old_paths[] = owm_files_path($blog['Old']['BlogID']);
					$new_paths[] = owm_sites_path($blog['New']['BlogID']);

					// New sites dir style
					$old_paths[] = owm_sites_path($blog['Old']['BlogID']);
					$new_paths[] = owm_sites_path($blog['New']['BlogID']);
				}
			}
		}

		// Set base site extract paths (should be added at the end of arrays)
		foreach ($blogs as $blog) {
			if (owm_main_site($blog['Old']['BlogID']) === true) {
				if (defined('UPLOADBLOGSDIR')) {
					// Old sites dir style
					$old_paths[] = owm_files_path($blog['Old']['BlogID']);
					$new_paths[] = owm_files_path($blog['New']['BlogID']);

					// New sites dir style
					$old_paths[] = owm_sites_path($blog['Old']['BlogID']);
					$new_paths[] = owm_files_path($blog['New']['BlogID']);
				} else {
					// Old sites dir style
					$old_paths[] = owm_files_path($blog['Old']['BlogID']);
					$new_paths[] = owm_sites_path($blog['New']['BlogID']);

					// New sites dir style
					$old_paths[] = owm_sites_path($blog['Old']['BlogID']);
					$new_paths[] = owm_sites_path($blog['New']['BlogID']);
				}
			}
		}

		while ($archive->has_not_reached_eof()) {
			$file_bytes_written = 0;

			// Exclude WordPress files
			$exclude_files = array_keys(_get_dropins());

			// Exclude plugin files and running migration plugin directory so archive never overwrites active plugin code
			$exclude_files = array_merge(
				$exclude_files,
				function_exists('owm_plugin_filters') ? owm_plugin_filters() : array(),
				array(
					OWM_PACKAGE_NAME,
					OWM_MULTISITE_NAME,
					OWM_DATABASE_NAME,
					OWM_MUPLUGINS_NAME,
					OWM_BACKUPS_NAME,
					'plugins' . DIRECTORY_SEPARATOR . 'onward-web-migration',
					'plugins' . DIRECTORY_SEPARATOR . 'all-in-one-wp-migration-unlimited-main',
				)
			);

			// Extract a file from archive to WP_CONTENT_DIR
			if (($completed = $archive->extract_one_file_to(WP_CONTENT_DIR, $exclude_files, $old_paths, $new_paths, $file_bytes_written, $file_bytes_offset))) {
				$file_bytes_offset = 0;
			}

			// Get archive bytes offset
			$archive_bytes_offset = $archive->get_file_pointer();

			// Increment processed files size
			$processed_files_size += $file_bytes_written;

			// What percent of files have we processed?
			$progress = (int) min(($processed_files_size / $total_files_size) * 100, 100);

			// Set progress
			/* translators: 1: total files count, 2: progress percentage */
			Owm_Status::info(sprintf(__('Restoring %1$d files...<br />%2$d%% complete', 'onward-web-migration'), $total_files_count, $progress));

			// Keep execution timer alive during file extraction
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Keep execution timer alive during file extraction.
			@set_time_limit(60);

			// Large file paused mid-stream: break immediately so file_bytes_offset is preserved
			if (!$completed) {
				break;
			}

			// More than 8 seconds have passed, break and do another request to stay well under web server proxy timeouts
			if (($timeout = apply_filters('owm_completed_timeout', 8))) {
				if ((microtime(true) - $start) > $timeout) {
					$completed = false;
					break;
				}
			}
		}

		// End of the archive?
		if ($archive->has_reached_eof()) {

			// Unset archive bytes offset
			unset($params['archive_bytes_offset']);

			// Unset file bytes offset
			unset($params['file_bytes_offset']);

			// Unset processed files size
			unset($params['processed_files_size']);

			// Unset total files size
			unset($params['total_files_size']);

			// Unset total files count
			unset($params['total_files_count']);

			// Unset completed flag
			unset($params['completed']);

		} else {

			// Set archive bytes offset
			$params['archive_bytes_offset'] = $archive_bytes_offset;

			// Set file bytes offset
			$params['file_bytes_offset'] = $file_bytes_offset;

			// Set processed files size
			$params['processed_files_size'] = $processed_files_size;

			// Set total files size
			$params['total_files_size'] = $total_files_size;

			// Set total files count
			$params['total_files_count'] = $total_files_count;

			// Set completed flag
			$params['completed'] = $completed;
		}

		// Close the archive file
		$archive->close();

		return $params;
	}
}
