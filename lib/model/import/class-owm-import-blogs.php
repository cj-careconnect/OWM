<?php
/**
 * Import Blogs
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Blogs
{

	public static function execute($params)
	{

		// Set progress
		Owm_Status::info(__('Preparing blogs...', 'onward-web-migration'));

		// Persist user-selected plugins and theme into package.json if passed in params
		if ((isset($params['selected_plugins']) || isset($params['selected_theme']) || !empty($params['preserve_current_theme'])) && is_file(owm_package_path($params))) {
			$handle = owm_open(owm_package_path($params), 'r');
			$pkg = json_decode(owm_read($handle, filesize(owm_package_path($params))), true);
			owm_close($handle);

			if (is_array($pkg)) {
				if (isset($params['selected_plugins']) && is_array($params['selected_plugins'])) {
					$pkg['Plugins'] = array_values(array_map('sanitize_text_field', $params['selected_plugins']));
					$pkg['UserSelectedPlugins'] = true;
				}

				if (!empty($params['preserve_current_theme']) || (isset($params['selected_theme']) && $params['selected_theme'] === 'current')) {
					$pkg['PreserveCurrentTheme'] = true;
					$pkg['CurrentTemplate']      = get_template();
					$pkg['CurrentStylesheet']    = get_stylesheet();
				}

				$handle = owm_open(owm_package_path($params), 'w');
				owm_write($handle, wp_json_encode($pkg));
				owm_close($handle);
			}
		}

		$blogs = array();

		// Check multisite.json file
		if (true === is_file(owm_multisite_path($params))) {

			// Read multisite.json file
			$handle = owm_open(owm_multisite_path($params), 'r');

			// Parse multisite.json file
			$multisite = owm_read($handle, filesize(owm_multisite_path($params)));
			$multisite = json_decode($multisite, true);

			// Close handle
			owm_close($handle);

			// Validate
			if (empty($multisite['Network'])) {
				if (isset($multisite['Sites']) && ($sites = $multisite['Sites'])) {
					if (count($sites) === 1 && ($subsite = current($sites))) {

						// Set internal Site URL (backward compatibility)
						if (empty($subsite['InternalSiteURL'])) {
							$subsite['InternalSiteURL'] = null;
						}

						// Set internal Home URL (backward compatibility)
						if (empty($subsite['InternalHomeURL'])) {
							$subsite['InternalHomeURL'] = null;
						}

						// Set active plugins (backward compatibility)
						if (empty($subsite['Plugins'])) {
							$subsite['Plugins'] = array();
						}

						// Set active template (backward compatibility)
						if (empty($subsite['Template'])) {
							$subsite['Template'] = null;
						}

						// Set active stylesheet (backward compatibility)
						if (empty($subsite['Stylesheet'])) {
							$subsite['Stylesheet'] = null;
						}

						// Set blog items
						$blogs[] = array(
							'Old' => array(
								'BlogID' => $subsite['BlogID'],
								'SiteURL' => $subsite['SiteURL'],
								'HomeURL' => $subsite['HomeURL'],
								'InternalSiteURL' => $subsite['InternalSiteURL'],
								'InternalHomeURL' => $subsite['InternalHomeURL'],
								'Plugins' => $subsite['Plugins'],
								'Template' => $subsite['Template'],
								'Stylesheet' => $subsite['Stylesheet'],
							),
							'New' => array(
								'BlogID' => null,
								'SiteURL' => site_url(),
								'HomeURL' => home_url(),
								'InternalSiteURL' => site_url(),
								'InternalHomeURL' => home_url(),
								'Plugins' => $subsite['Plugins'],
								'Template' => $subsite['Template'],
								'Stylesheet' => $subsite['Stylesheet'],
							),
						);
					} else {
						throw new Owm_Import_Exception(
							wp_kses_post(
								__('The archive should contain <strong>Single WordPress</strong> site! Please revisit your export settings.', 'onward-web-migration')
							)
						);
					}
				} else {
					throw new Owm_Import_Exception(
						wp_kses_post(
							__('At least <strong>one WordPress</strong> site should be presented in the archive.', 'onward-web-migration')
						)
					);
				}
			} else {
				throw new Owm_Import_Exception(
					wp_kses_post(
						__('Unable to import <strong>WordPress Network</strong> into WordPress <strong>Single</strong> site.', 'onward-web-migration')
					)
				);
			}
		}

		// Write blogs.json file
		$handle = owm_open(owm_blogs_path($params), 'w');
		owm_write($handle, wp_json_encode($blogs));
		owm_close($handle);

		// Set progress
		Owm_Status::info(__('Done preparing blogs.', 'onward-web-migration'));

		return $params;
	}
}
