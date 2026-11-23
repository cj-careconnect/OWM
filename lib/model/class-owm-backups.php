<?php
/**

 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
*
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Backups
{

	/**
	 * Get all backup files
	 *
	 * @return array
	 */
	public function get_files()
	{
		$backups = array();

		if (!is_dir(OWM_BACKUPS_PATH)) {
			wp_mkdir_p(OWM_BACKUPS_PATH);
			return $backups;
		}

		// Iterate over directory
		$iterator = new Owm_Recursive_Directory_Iterator(OWM_BACKUPS_PATH);

		// Filter by extensions (supports both .zip and .wpress)
		$iterator = new Owm_Recursive_Extension_Filter($iterator, array('zip', 'wpress'));

		// Recursively iterate over directory
		$iterator = new Owm_Recursive_Iterator_Iterator($iterator, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);

		// Get backup files
		foreach ($iterator as $item) {
			try {
				if (owm_is_filesize_supported($item->getPathname())) {
					$backups[] = array(
						'path' => $iterator->getSubPath(),
						'filename' => $iterator->getSubPathname(),
						'mtime' => $iterator->getMTime(),
						'size' => $iterator->getSize(),
					);
				} else {
					$backups[] = array(
						'path' => $iterator->getSubPath(),
						'filename' => $iterator->getSubPathname(),
						'mtime' => $iterator->getMTime(),
						'size' => null,
					);
				}
			} catch (Exception $e) {
				$backups[] = array(
					'path' => $iterator->getSubPath(),
					'filename' => $iterator->getSubPathname(),
					'mtime' => null,
					'size' => null,
				);
			}
		}

		// Sort backups modified date
		usort($backups, array($this, 'compare'));

		return $backups;
	}

	/**
	 * Delete file
	 *
	 * @param  string  $file File name
	 * @return boolean
	 */
	public function delete_file($file)
	{
		if (validate_file($file) === 0) {
			return wp_delete_file(owm_backup_path(array('archive' => $file)));
		}
	}

	/**
	 * Compare backup files by modified time
	 *
	 * @param  array $a File item A
	 * @param  array $b File item B
	 * @return integer
	 */
	public function compare($a, $b)
	{
		if ($a['mtime'] === $b['mtime']) {
			return 0;
		}

		return ($a['mtime'] > $b['mtime']) ? -1 : 1;
	}
}
