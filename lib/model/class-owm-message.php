<?php
/**
 * Message Model
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Message
{

	public static function flash($type, $message)
	{
		if (($messages = get_option(OWM_MESSAGES, array())) !== false) {
			return update_option(OWM_MESSAGES, array_merge($messages, array($type => $message)));
		}

		return false;
	}

	public static function has($type)
	{
		if (($messages = get_option(OWM_MESSAGES, array()))) {
			if (isset($messages[$type])) {
				return true;
			}
		}

		return false;
	}

	public static function get($type)
	{
		$message = null;
		if (($messages = get_option(OWM_MESSAGES, array()))) {
			if (isset($messages[$type]) && ($message = $messages[$type])) {
				unset($messages[$type]);
			}

			// Set messages
			update_option(OWM_MESSAGES, $messages);
		}

		return $message;
	}
}
