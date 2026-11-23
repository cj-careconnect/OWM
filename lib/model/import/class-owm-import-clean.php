<?php
/**
 * Import Clean
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Clean
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
