<?php
/**
 * Status Model
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Status
{

	public static function error($title, $message, $debug = null)
	{
		$payload = array('type' => 'error', 'title' => $title, 'message' => $message);
		if ($debug !== null) {
			$payload['debug'] = $debug;
		} elseif (class_exists('Owm_Handler') && method_exists('Owm_Handler', 'get_debug_report')) {
			$payload['debug'] = Owm_Handler::get_debug_report();
		}
		self::log($payload);
	}

	public static function info($message)
	{
		self::log(array('type' => 'info', 'message' => $message));
	}

	public static function download($message)
	{
		self::log(array('type' => 'download', 'message' => $message));
	}

	public static function confirm($message, $extra = array())
	{
		$data = array('type' => 'confirm', 'message' => $message);
		if (is_array($extra) && !empty($extra)) {
			$data = array_merge($data, $extra);
		}
		self::log($data);
	}

	public static function done($title, $message)
	{
		self::log(array('type' => 'done', 'title' => $title, 'message' => $message));
	}

	public static function blogs($title, $message)
	{
		self::log(array('type' => 'blogs', 'title' => $title, 'message' => $message));
	}

	public static function progress($percent)
	{
		self::log(array('type' => 'progress', 'percent' => $percent));
	}

	public static function log($data)
	{
		if (!owm_is_scheduled_backup()) {
			update_option(OWM_STATUS, $data);
		}
	}
}
