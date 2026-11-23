<?php
/**
 * WP Sync & Backup - Export Download
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Export_Download
{

	public static function execute($params)
	{

		// Set progress
		Owm_Status::info(__('Renaming exported file...', 'onward-web-migration'));

		// Open the archive file for writing
		$archive = new Owm_Compressor(owm_archive_path($params));

		// Append EOF block
		$archive->close(true);

		// Ensure backup directory exists
		if (!is_dir(OWM_BACKUPS_PATH)) {
			wp_mkdir_p(OWM_BACKUPS_PATH);
		}

		$source = owm_archive_path($params);
		$dest   = owm_backup_path($params);

		// Rename archive file with fallback to copy and delete
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$renamed = false;
		if ($wp_filesystem) {
			$renamed = $wp_filesystem->move($source, $dest, true);
		}
		if (!$renamed && is_file($source)) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Fallback if WP_Filesystem move fails
			$renamed = @rename($source, $dest);
			if (!$renamed) {
				$renamed = @copy($source, $dest);
				if ($renamed) {
					wp_delete_file($source);
				}
			}
		}

		// Check if archive was successfully moved to backups
		if ($renamed) {

			$blog_id = null;

			// Get subsite Blog ID
			if (isset($params['options']['sites']) && ($sites = $params['options']['sites'])) {
				if (count($sites) === 1) {
					$blog_id = array_shift($sites);
				}
			}

			// Set archive details
			$link = owm_backup_url($params);
			$size = owm_backup_size($params);
			$name = owm_site_name($blog_id);

			// Set progress
			Owm_Status::download(
				sprintf(
					'<a href="%1$s" class="owm-button-green owm-emphasize"><span>%2$s</span><em>%3$s</em></a>',
					esc_url( $link ),
					/* translators: %s: site name */
					sprintf( __( 'Download %s', 'onward-web-migration' ), esc_html( $name ) ),
					/* translators: %s: file size */
					sprintf( __( 'Size: %s', 'onward-web-migration' ), esc_html( $size ) )
				)
			);
		}

		return $params;
	}
}
