<?php
/**
 * WP Sync & Backup - Export Enumerate
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Export_Enumerate
{

	public static function execute($params)
	{

		// Get total files count
		if (isset($params['total_files_count'])) {
			$total_files_count = (int) $params['total_files_count'];
		} else {
			$total_files_count = 0;
		}

		// Get total files size
		if (isset($params['total_files_size'])) {
			$total_files_size = (int) $params['total_files_size'];
		} else {
			$total_files_size = 0;
		}

		// Set progress
		Owm_Status::info(__('Retrieving a list of all WordPress files...', 'onward-web-migration'));

		// Set exclude filters
		$exclude_filters = owm_content_filters();

		// Exclude cache
		if (isset($params['options']['no_cache'])) {
			$exclude_filters[] = 'cache';
		}

		// Exclude themes
		if (isset($params['options']['no_themes'])) {
			$exclude_filters[] = 'themes';
		} else {
			$inactive_themes = array();

			// Exclude inactive themes
			if (isset($params['options']['no_inactive_themes'])) {
				foreach (wp_get_themes() as $theme => $info) {
					// Exclude current parent and child themes
					if (!in_array($theme, array(get_template(), get_stylesheet()))) {
						$inactive_themes[] = 'themes' . DIRECTORY_SEPARATOR . $theme;
					}
				}
			}

			// Set exclude filters
			$exclude_filters = array_merge($exclude_filters, $inactive_themes);
		}

		// Exclude must-use plugins
		if (isset($params['options']['no_muplugins'])) {
			$exclude_filters = array_merge($exclude_filters, array('mu-plugins'));
		}

		// Exclude plugins
		if (isset($params['options']['no_plugins'])) {
			$exclude_filters = array_merge($exclude_filters, array('plugins'));
		} else {
			$inactive_plugins = array();

			// Exclude inactive plugins
			if (isset($params['options']['no_inactive_plugins'])) {
				foreach (get_plugins() as $plugin => $info) {
					if (is_plugin_inactive($plugin)) {
						$inactive_plugins[] = 'plugins' . DIRECTORY_SEPARATOR .
							((dirname($plugin) === '.') ? basename($plugin) : dirname($plugin));
					}
				}
			}

			// Set exclude filters
			$exclude_filters = array_merge($exclude_filters, owm_plugin_filters($inactive_plugins));
		}

		// Exclude media
		if (isset($params['options']['no_media'])) {
			$exclude_filters = array_merge($exclude_filters, array('uploads', 'blogs.dir'));
		}

		// Create map file
		$filemap = owm_open(owm_filemap_path($params), 'w');

		$content_root = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';

		// Targets to enumerate: always includes WP_CONTENT_DIR, plus configured external locations
		$targets = array(
			array(
				'dir'    => $content_root,
				'prefix' => '',
			),
		);

		// Enumerate WP_PLUGIN_DIR if configured outside WP_CONTENT_DIR
		if (defined('WP_PLUGIN_DIR') && is_dir(WP_PLUGIN_DIR) && !isset($params['options']['no_plugins'])) {
			$norm_content = function_exists('wp_normalize_path') ? wp_normalize_path($content_root) : str_replace('\\', '/', $content_root);
			$norm_plugin  = function_exists('wp_normalize_path') ? wp_normalize_path(WP_PLUGIN_DIR) : str_replace('\\', '/', WP_PLUGIN_DIR);
			if (strpos($norm_plugin, $norm_content) !== 0) {
				$targets[] = array(
					'dir'    => WP_PLUGIN_DIR,
					'prefix' => 'plugins' . DIRECTORY_SEPARATOR,
				);
			}
		}

		// Enumerate wp_upload_dir() if configured outside WP_CONTENT_DIR
		$upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : null;
		if (!empty($upload_dir['basedir']) && is_dir($upload_dir['basedir']) && !isset($params['options']['no_media'])) {
			$norm_content = function_exists('wp_normalize_path') ? wp_normalize_path($content_root) : str_replace('\\', '/', $content_root);
			$norm_upload  = function_exists('wp_normalize_path') ? wp_normalize_path($upload_dir['basedir']) : str_replace('\\', '/', $upload_dir['basedir']);
			if (strpos($norm_upload, $norm_content) !== 0) {
				$targets[] = array(
					'dir'    => $upload_dir['basedir'],
					'prefix' => 'uploads' . DIRECTORY_SEPARATOR,
				);
			}
		}

		// Iterate over configured targets
		foreach ($targets as $target) {
			if (!is_dir($target['dir'])) {
				continue;
			}

			// Iterate over directory
			$iterator = new Owm_Recursive_Directory_Iterator($target['dir']);

			// Exclude new line file names
			$iterator = new Owm_Recursive_Newline_Filter($iterator);

			// Exclude uploads, plugins or themes
			$iterator = new Owm_Recursive_Exclude_Filter($iterator, apply_filters('owm_exclude_content_from_export', $exclude_filters));

			// Recursively iterate over directory
			$iterator = new Owm_Recursive_Iterator_Iterator($iterator, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);

			// Write path line
			foreach ($iterator as $item) {
				if ($item->isFile()) {
					$rel_path = $target['prefix'] . $iterator->getSubPathName();
					if (owm_write($filemap, $rel_path . PHP_EOL)) {
						$total_files_count++;

						// Add current file size
						$total_files_size += $iterator->getSize();
					}
				}
			}
		}

		// Set progress
		Owm_Status::info(__('Done retrieving a list of all WordPress files.', 'onward-web-migration'));

		// Set total files count
		$params['total_files_count'] = $total_files_count;

		// Set total files size
		$params['total_files_size'] = $total_files_size;

		// Close the filemap file
		owm_close($filemap);

		return $params;
	}
}
