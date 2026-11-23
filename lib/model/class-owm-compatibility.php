<?php
/**
 * Compatibility Model
 *
 * @package Onward_Web_Migration
 */

if (!defined('ABSPATH')) {
	exit;
}

class Owm_Compatibility
{

	public static function get($params)
	{
		return array();
	}

	public static function check($extension)
	{
		return true;
	}
}
