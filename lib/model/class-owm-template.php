<?php
/**
 * Template Model
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Template extends Bandar
{

	/**
	 * Renders a file and returns its contents
	 *
	 * @param  string $file View to render
	 * @param  array  $args Set of arguments
	 *
	 * @return string       Rendered view
	 */
	public static function render($view, $args = array(), $path = false)
	{
		parent::render($view, $args, $path);
	}

	/**
	 * Returns link to an asset file
	 *
	 * @param  string $asset Asset file
	 *
	 * @return string        Asset URL
	 */
	public static function asset_link($asset, $prefix = 'OWM')
	{
		return constant($prefix . '_URL') . '/lib/view/assets/' . $asset . '?v=' . constant($prefix . '_VERSION');
	}

	public static function get_content($template, $args = array(), $path = false)
	{
		return parent::getTemplateContent($template, $args, $path);
	}
}
