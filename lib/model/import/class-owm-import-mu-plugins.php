<?php
/**
 * Import MU Plugins
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Mu_Plugins
{

	public static function execute($params)
	{
		// To comply with WordPress.org security guidelines and prevent arbitrary code execution,
		// archive imports are strictly prohibited from placing executable PHP files into mu-plugins.
		return $params;
	}
}
