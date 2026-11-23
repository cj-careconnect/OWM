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
 */

if (!defined('ABSPATH')) {
    exit;
}

class Owm_Log
{

    /**
     * Sanitize parameters before logging to ensure secret keys and credentials are never logged.
     *
     * @param  mixed $params
     * @return mixed
     */
    private static function sanitize_log_params($params)
    {
        if (!is_array($params)) {
            return $params;
        }

        $sanitized = $params;
        unset(
            $sanitized['secret_key'],
            $sanitized['owm_nonce'],
            $sanitized['_wpnonce'],
            $sanitized['password'],
            $sanitized['auth_password'],
            $sanitized['auth_user']
        );

        return $sanitized;
    }

    public static function export($params)
    {
        $data = array();

        // Add date
        $data[] = gmdate('M d Y H:i:s');

        // Add sanitized params
        $data[] = wp_json_encode(self::sanitize_log_params($params));

        // Add empty line
        $data[] = PHP_EOL;

        // Write log data
        if ($handle = owm_open(owm_export_path($params), 'a')) {
            owm_write($handle, implode(PHP_EOL, $data));
            owm_close($handle);
        }
    }

    public static function import($params)
    {
        $data = array();

        // Add date
        $data[] = gmdate('M d Y H:i:s');

        // Add sanitized params
        $data[] = wp_json_encode(self::sanitize_log_params($params));

        // Add empty line
        $data[] = PHP_EOL;

        // Write log data
        if ($handle = owm_open(owm_import_path($params), 'a')) {
            owm_write($handle, implode(PHP_EOL, $data));
            owm_close($handle);
        }
    }

    public static function error($params)
    {
        $data = array();

        // Add date
        $data[] = gmdate('M d Y H:i:s');

        // Add sanitized params
        $data[] = wp_json_encode(self::sanitize_log_params($params));

        // Add empty line
        $data[] = PHP_EOL;

        // Write log data
        if ($handle = owm_open(owm_error_path(), 'a')) {
            owm_write($handle, implode(PHP_EOL, $data));
            owm_close($handle);
        }
    }
}
