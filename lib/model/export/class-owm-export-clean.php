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

class Owm_Export_Clean
{

	public static function execute($params)
	{
		if (class_exists('Owm_Handler')) {
			Owm_Handler::mark_completed();
		}
		if (!empty($params['storage'])) {
			try {
				Owm_Directory::delete(owm_storage_path($params));
			} catch (Exception $e) {
			} catch (Throwable $e) {
			}
		}
		echo wp_json_encode(array('completed' => true));
		exit;
	}
}
