<?php
/**
 * Import Database
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Import_Database
{

	public static function execute($params)
	{
		global $wpdb;

		// Raise PHP execution time and memory limits for heavy database imports
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for long-running database import operations.
		@set_time_limit(0);
		if (function_exists('wp_raise_memory_limit')) {
			@wp_raise_memory_limit('admin');
		}

		// Skip database import
		if (!is_file(owm_database_path($params))) {
			return $params;
		}

		// Set query offset
		if (isset($params['query_offset'])) {
			$query_offset = (int) $params['query_offset'];
		} else {
			$query_offset = 0;
		}

		// Set total queries size
		if (isset($params['total_queries_size'])) {
			$total_queries_size = (int) $params['total_queries_size'];
		} else {
			$total_queries_size = 1;
		}

		// Read blogs.json file
		$handle = owm_open(owm_blogs_path($params), 'r');

		// Parse blogs.json file
		$blogs = owm_read($handle, filesize(owm_blogs_path($params)));
		$blogs = json_decode($blogs, true);

		// Close handle
		owm_close($handle);

		// Read package.json file
		$handle = owm_open(owm_package_path($params), 'r');

		// Parse package.json file
		$config = owm_read($handle, filesize(owm_package_path($params)));
		$config = json_decode($config, true);

		// Close handle
		owm_close($handle);

		// What percent of queries have we processed?
		$progress = (int) (($query_offset / $total_queries_size) * 100);

		// Set progress
		/* translators: %d: progress percentage */
		Owm_Status::info(sprintf(__('Restoring database...<br />%d%% complete', 'onward-web-migration'), $progress));

		$old_replace_values = $old_replace_raw_values = array();
		$new_replace_values = $new_replace_raw_values = array();

		// Get Blog URLs
		foreach ($blogs as $blog) {

			$home_urls = array();

			// Add Home URL
			if (!empty($blog['Old']['HomeURL'])) {
				$home_urls[] = $blog['Old']['HomeURL'];
			}

			// Add Internal Home URL
			if (!empty($blog['Old']['InternalHomeURL'])) {
				if (wp_parse_url($blog['Old']['InternalHomeURL'], PHP_URL_SCHEME) && wp_parse_url($blog['Old']['InternalHomeURL'], PHP_URL_HOST)) {
					$home_urls[] = $blog['Old']['InternalHomeURL'];
				}
			}

			// Get Home URL
			foreach ($home_urls as $home_url) {

				// Get blogs dir Upload Path
				if (!in_array(sprintf("'%s'", trim(owm_blogsdir_path($blog['Old']['BlogID']), '/')), $old_replace_raw_values)) {
					$old_replace_raw_values[] = sprintf("'%s'", trim(owm_blogsdir_path($blog['Old']['BlogID']), '/'));
					$new_replace_raw_values[] = sprintf("'%s'", get_option('upload_path'));
				}

				// Get sites dir Upload Path
				if (!in_array(sprintf("'%s'", trim(owm_uploads_path($blog['Old']['BlogID']), '/')), $old_replace_raw_values)) {
					$old_replace_raw_values[] = sprintf("'%s'", trim(owm_uploads_path($blog['Old']['BlogID']), '/'));
					$new_replace_raw_values[] = sprintf("'%s'", get_option('upload_path'));
				}

				// Handle old and new sites dir style
				if (defined('UPLOADBLOGSDIR')) {

					// Get plain Upload Path
					if (!in_array(owm_blogsdir_path($blog['Old']['BlogID']), $old_replace_values)) {
						$old_replace_values[] = owm_blogsdir_path($blog['Old']['BlogID']);
						$new_replace_values[] = owm_blogsdir_path($blog['New']['BlogID']);
					}

					// Get URL encoded Upload Path
					if (!in_array(urlencode(owm_blogsdir_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = urlencode(owm_blogsdir_path($blog['Old']['BlogID']));
						$new_replace_values[] = urlencode(owm_blogsdir_path($blog['New']['BlogID']));
					}

					// Get URL raw encoded Upload Path
					if (!in_array(rawurlencode(owm_blogsdir_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = rawurlencode(owm_blogsdir_path($blog['Old']['BlogID']));
						$new_replace_values[] = rawurlencode(owm_blogsdir_path($blog['New']['BlogID']));
					}

					// Get JSON escaped Upload Path
					if (!in_array(addcslashes(owm_blogsdir_path($blog['Old']['BlogID']), '/'), $old_replace_values)) {
						$old_replace_values[] = addcslashes(owm_blogsdir_path($blog['Old']['BlogID']), '/');
						$new_replace_values[] = addcslashes(owm_blogsdir_path($blog['New']['BlogID']), '/');
					}

					// Get plain Upload Path
					if (!in_array(owm_uploads_path($blog['Old']['BlogID']), $old_replace_values)) {
						$old_replace_values[] = owm_uploads_path($blog['Old']['BlogID']);
						$new_replace_values[] = owm_blogsdir_path($blog['New']['BlogID']);
					}

					// Get URL encoded Upload Path
					if (!in_array(urlencode(owm_uploads_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = urlencode(owm_uploads_path($blog['Old']['BlogID']));
						$new_replace_values[] = urlencode(owm_blogsdir_path($blog['New']['BlogID']));
					}

					// Get URL raw encoded Upload Path
					if (!in_array(rawurlencode(owm_uploads_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = rawurlencode(owm_uploads_path($blog['Old']['BlogID']));
						$new_replace_values[] = rawurlencode(owm_blogsdir_path($blog['New']['BlogID']));
					}

					// Get JSON escaped Upload Path
					if (!in_array(addcslashes(owm_uploads_path($blog['Old']['BlogID']), '/'), $old_replace_values)) {
						$old_replace_values[] = addcslashes(owm_uploads_path($blog['Old']['BlogID']), '/');
						$new_replace_values[] = addcslashes(owm_blogsdir_path($blog['New']['BlogID']), '/');
					}
				} else {

					// Get files dir Upload URL
					if (!in_array(sprintf('%s/%s/', untrailingslashit($home_url), 'files'), $old_replace_values)) {
						$old_replace_values[] = sprintf('%s/%s/', untrailingslashit($home_url), 'files');
						$new_replace_values[] = owm_uploads_url($blog['New']['BlogID']);
					}

					// Get plain Upload Path
					if (!in_array(owm_blogsdir_path($blog['Old']['BlogID']), $old_replace_values)) {
						$old_replace_values[] = owm_blogsdir_path($blog['Old']['BlogID']);
						$new_replace_values[] = owm_uploads_path($blog['New']['BlogID']);
					}

					// Get URL encoded Upload Path
					if (!in_array(urlencode(owm_blogsdir_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = urlencode(owm_blogsdir_path($blog['Old']['BlogID']));
						$new_replace_values[] = urlencode(owm_uploads_path($blog['New']['BlogID']));
					}

					// Get URL raw encoded Upload Path
					if (!in_array(rawurlencode(owm_blogsdir_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = rawurlencode(owm_blogsdir_path($blog['Old']['BlogID']));
						$new_replace_values[] = rawurlencode(owm_uploads_path($blog['New']['BlogID']));
					}

					// Get JSON escaped Upload Path
					if (!in_array(addcslashes(owm_blogsdir_path($blog['Old']['BlogID']), '/'), $old_replace_values)) {
						$old_replace_values[] = addcslashes(owm_blogsdir_path($blog['Old']['BlogID']), '/');
						$new_replace_values[] = addcslashes(owm_uploads_path($blog['New']['BlogID']), '/');
					}

					// Get plain Upload Path
					if (!in_array(owm_uploads_path($blog['Old']['BlogID']), $old_replace_values)) {
						$old_replace_values[] = owm_uploads_path($blog['Old']['BlogID']);
						$new_replace_values[] = owm_uploads_path($blog['New']['BlogID']);
					}

					// Get URL encoded Upload Path
					if (!in_array(urlencode(owm_uploads_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = urlencode(owm_uploads_path($blog['Old']['BlogID']));
						$new_replace_values[] = urlencode(owm_uploads_path($blog['New']['BlogID']));
					}

					// Get URL raw encoded Upload Path
					if (!in_array(rawurlencode(owm_uploads_path($blog['Old']['BlogID'])), $old_replace_values)) {
						$old_replace_values[] = rawurlencode(owm_uploads_path($blog['Old']['BlogID']));
						$new_replace_values[] = rawurlencode(owm_uploads_path($blog['New']['BlogID']));
					}

					// Get JSON escaped Upload Path
					if (!in_array(addcslashes(owm_uploads_path($blog['Old']['BlogID']), '/'), $old_replace_values)) {
						$old_replace_values[] = addcslashes(owm_uploads_path($blog['Old']['BlogID']), '/');
						$new_replace_values[] = addcslashes(owm_uploads_path($blog['New']['BlogID']), '/');
					}
				}
			}

			$site_urls = array();

			// Add Site URL
			if (!empty($blog['Old']['SiteURL'])) {
				$site_urls[] = $blog['Old']['SiteURL'];
			}

			// Add Internal Site URL
			if (!empty($blog['Old']['InternalSiteURL'])) {
				if (wp_parse_url($blog['Old']['InternalSiteURL'], PHP_URL_SCHEME) && wp_parse_url($blog['Old']['InternalSiteURL'], PHP_URL_HOST)) {
					$site_urls[] = $blog['Old']['InternalSiteURL'];
				}
			}

			// Get Site URL
			foreach ($site_urls as $site_url) {

				// Replace Site URL
				if ($site_url !== $blog['New']['SiteURL']) {

					// Get www URL
					if (stripos($site_url, '//www.') !== false) {
						$site_url_www_inversion = str_ireplace('//www.', '//', $site_url);
					} else {
						$site_url_www_inversion = str_ireplace('//', '//www.', $site_url);
					}

					// Replace Site URL
					foreach (array($site_url, $site_url_www_inversion) as $url) {

						// Get domain
						$old_domain = wp_parse_url($url, PHP_URL_HOST);
						$new_domain = wp_parse_url($blog['New']['SiteURL'], PHP_URL_HOST);

						// Get path
						$old_path = wp_parse_url($url, PHP_URL_PATH);
						$new_path = wp_parse_url($blog['New']['SiteURL'], PHP_URL_PATH);

						// Get scheme
						$new_scheme = wp_parse_url($blog['New']['SiteURL'], PHP_URL_SCHEME);

						// Add domain and path
						if (!in_array(sprintf("'%s','%s'", $old_domain, trailingslashit($old_path)), $old_replace_raw_values)) {
							$old_replace_raw_values[] = sprintf("'%s','%s'", $old_domain, trailingslashit($old_path));
							$new_replace_raw_values[] = sprintf("'%s','%s'", $new_domain, trailingslashit($new_path));
						}

						// Add domain and path with single quote
						if (!in_array(sprintf("='%s%s", $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
							$old_replace_values[] = sprintf("='%s%s", $old_domain, untrailingslashit($old_path));
							$new_replace_values[] = sprintf("='%s%s", $new_domain, untrailingslashit($new_path));
						}

						// Add domain and path with double quote
						if (!in_array(sprintf('="%s%s', $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
							$old_replace_values[] = sprintf('="%s%s', $old_domain, untrailingslashit($old_path));
							$new_replace_values[] = sprintf('="%s%s', $new_domain, untrailingslashit($new_path));
						}

						// Add Site URL scheme
						$old_schemes = array('http', 'https', '');
						$new_schemes = array($new_scheme, $new_scheme, '');

						// Replace Site URL scheme
						for ($i = 0; $i < count($old_schemes); $i++) {

							// Add plain Site URL
							if (!in_array(owm_url_scheme($url, $old_schemes[$i]), $old_replace_values)) {
								$old_replace_values[] = owm_url_scheme($url, $old_schemes[$i]);
								$new_replace_values[] = owm_url_scheme($blog['New']['SiteURL'], $new_schemes[$i]);
							}

							// Add URL encoded Site URL
							if (!in_array(urlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
								$old_replace_values[] = urlencode(owm_url_scheme($url, $old_schemes[$i]));
								$new_replace_values[] = urlencode(owm_url_scheme($blog['New']['SiteURL'], $new_schemes[$i]));
							}

							// Add URL raw encoded Site URL
							if (!in_array(rawurlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
								$old_replace_values[] = rawurlencode(owm_url_scheme($url, $old_schemes[$i]));
								$new_replace_values[] = rawurlencode(owm_url_scheme($blog['New']['SiteURL'], $new_schemes[$i]));
							}

							// Add JSON escaped Site URL
							if (!in_array(addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/'), $old_replace_values)) {
								$old_replace_values[] = addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/');
								$new_replace_values[] = addcslashes(owm_url_scheme($blog['New']['SiteURL'], $new_schemes[$i]), '/');
							}
						}

						// Add email
						if (!isset($config['NoEmailReplace'])) {
							if (!in_array(sprintf('@%s', $old_domain), $old_replace_values)) {
								$old_replace_values[] = sprintf('@%s', $old_domain);
								$new_replace_values[] = sprintf('@%s', $new_domain);
							}
						}
					}
				}
			}

			$home_urls = array();

			// Add Home URL
			if (!empty($blog['Old']['HomeURL'])) {
				$home_urls[] = $blog['Old']['HomeURL'];
			}

			// Add Internal Home URL
			if (!empty($blog['Old']['InternalHomeURL'])) {
				if (wp_parse_url($blog['Old']['InternalHomeURL'], PHP_URL_SCHEME) && wp_parse_url($blog['Old']['InternalHomeURL'], PHP_URL_HOST)) {
					$home_urls[] = $blog['Old']['InternalHomeURL'];
				}
			}

			// Get Home URL
			foreach ($home_urls as $home_url) {

				// Replace Home URL
				if ($home_url !== $blog['New']['HomeURL']) {

					// Get www URL
					if (stripos($home_url, '//www.') !== false) {
						$home_url_www_inversion = str_ireplace('//www.', '//', $home_url);
					} else {
						$home_url_www_inversion = str_ireplace('//', '//www.', $home_url);
					}

					// Replace Home URL
					foreach (array($home_url, $home_url_www_inversion) as $url) {

						// Get domain
						$old_domain = wp_parse_url($url, PHP_URL_HOST);
						$new_domain = wp_parse_url($blog['New']['HomeURL'], PHP_URL_HOST);

						// Get path
						$old_path = wp_parse_url($url, PHP_URL_PATH);
						$new_path = wp_parse_url($blog['New']['HomeURL'], PHP_URL_PATH);

						// Get scheme
						$new_scheme = wp_parse_url($blog['New']['HomeURL'], PHP_URL_SCHEME);

						// Add domain and path
						if (!in_array(sprintf("'%s','%s'", $old_domain, trailingslashit($old_path)), $old_replace_raw_values)) {
							$old_replace_raw_values[] = sprintf("'%s','%s'", $old_domain, trailingslashit($old_path));
							$new_replace_raw_values[] = sprintf("'%s','%s'", $new_domain, trailingslashit($new_path));
						}

						// Add domain and path with single quote
						if (!in_array(sprintf("='%s%s", $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
							$old_replace_values[] = sprintf("='%s%s", $old_domain, untrailingslashit($old_path));
							$new_replace_values[] = sprintf("='%s%s", $new_domain, untrailingslashit($new_path));
						}

						// Add domain and path with double quote
						if (!in_array(sprintf('="%s%s', $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
							$old_replace_values[] = sprintf('="%s%s', $old_domain, untrailingslashit($old_path));
							$new_replace_values[] = sprintf('="%s%s', $new_domain, untrailingslashit($new_path));
						}

						// Set Home URL scheme
						$old_schemes = array('http', 'https', '');
						$new_schemes = array($new_scheme, $new_scheme, '');

						// Replace Home URL scheme
						for ($i = 0; $i < count($old_schemes); $i++) {

							// Add plain Home URL
							if (!in_array(owm_url_scheme($url, $old_schemes[$i]), $old_replace_values)) {
								$old_replace_values[] = owm_url_scheme($url, $old_schemes[$i]);
								$new_replace_values[] = owm_url_scheme($blog['New']['HomeURL'], $new_schemes[$i]);
							}

							// Add URL encoded Home URL
							if (!in_array(urlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
								$old_replace_values[] = urlencode(owm_url_scheme($url, $old_schemes[$i]));
								$new_replace_values[] = urlencode(owm_url_scheme($blog['New']['HomeURL'], $new_schemes[$i]));
							}

							// Add URL raw encoded Home URL
							if (!in_array(rawurlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
								$old_replace_values[] = rawurlencode(owm_url_scheme($url, $old_schemes[$i]));
								$new_replace_values[] = rawurlencode(owm_url_scheme($blog['New']['HomeURL'], $new_schemes[$i]));
							}

							// Add JSON escaped Home URL
							if (!in_array(addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/'), $old_replace_values)) {
								$old_replace_values[] = addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/');
								$new_replace_values[] = addcslashes(owm_url_scheme($blog['New']['HomeURL'], $new_schemes[$i]), '/');
							}
						}

						// Add email
						if (!isset($config['NoEmailReplace'])) {
							if (!in_array(sprintf('@%s', $old_domain), $old_replace_values)) {
								$old_replace_values[] = sprintf('@%s', $old_domain);
								$new_replace_values[] = sprintf('@%s', $new_domain);
							}
						}
					}
				}
			}
		}

		$site_urls = array();

		// Add Site URL
		if (!empty($config['SiteURL'])) {
			$site_urls[] = $config['SiteURL'];
		}

		// Add Internal Site URL
		if (!empty($config['InternalSiteURL'])) {
			if (wp_parse_url($config['InternalSiteURL'], PHP_URL_SCHEME) && wp_parse_url($config['InternalSiteURL'], PHP_URL_HOST)) {
				$site_urls[] = $config['InternalSiteURL'];
			}
		}

		// Get Site URL
		foreach ($site_urls as $site_url) {

			// Replace Site URL
			if ($site_url !== site_url()) {

				// Get www URL
				if (stripos($site_url, '//www.') !== false) {
					$site_url_www_inversion = str_ireplace('//www.', '//', $site_url);
				} else {
					$site_url_www_inversion = str_ireplace('//', '//www.', $site_url);
				}

				// Replace Site URL
				foreach (array($site_url, $site_url_www_inversion) as $url) {

					// Get domain
					$old_domain = wp_parse_url($url, PHP_URL_HOST);
					$new_domain = wp_parse_url(site_url(), PHP_URL_HOST);

					// Get path
					$old_path = wp_parse_url($url, PHP_URL_PATH);
					$new_path = wp_parse_url(site_url(), PHP_URL_PATH);

					// Get scheme
					$new_scheme = wp_parse_url(site_url(), PHP_URL_SCHEME);

					// Add domain and path
					if (!in_array(sprintf("'%s','%s'", $old_domain, trailingslashit($old_path)), $old_replace_raw_values)) {
						$old_replace_raw_values[] = sprintf("'%s','%s'", $old_domain, trailingslashit($old_path));
						$new_replace_raw_values[] = sprintf("'%s','%s'", $new_domain, trailingslashit($new_path));
					}

					// Add domain and path with single quote
					if (!in_array(sprintf("='%s%s", $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
						$old_replace_values[] = sprintf("='%s%s", $old_domain, untrailingslashit($old_path));
						$new_replace_values[] = sprintf("='%s%s", $new_domain, untrailingslashit($new_path));
					}

					// Add domain and path with double quote
					if (!in_array(sprintf('="%s%s', $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
						$old_replace_values[] = sprintf('="%s%s', $old_domain, untrailingslashit($old_path));
						$new_replace_values[] = sprintf('="%s%s', $new_domain, untrailingslashit($new_path));
					}

					// Set Site URL scheme
					$old_schemes = array('http', 'https', '');
					$new_schemes = array($new_scheme, $new_scheme, '');

					// Replace Site URL scheme
					for ($i = 0; $i < count($old_schemes); $i++) {

						// Add plain Site URL
						if (!in_array(owm_url_scheme($url, $old_schemes[$i]), $old_replace_values)) {
							$old_replace_values[] = owm_url_scheme($url, $old_schemes[$i]);
							$new_replace_values[] = owm_url_scheme(site_url(), $new_schemes[$i]);
						}

						// Add URL encoded Site URL
						if (!in_array(urlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
							$old_replace_values[] = urlencode(owm_url_scheme($url, $old_schemes[$i]));
							$new_replace_values[] = urlencode(owm_url_scheme(site_url(), $new_schemes[$i]));
						}

						// Add URL raw encoded Site URL
						if (!in_array(rawurlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
							$old_replace_values[] = rawurlencode(owm_url_scheme($url, $old_schemes[$i]));
							$new_replace_values[] = rawurlencode(owm_url_scheme(site_url(), $new_schemes[$i]));
						}

						// Add JSON escaped Site URL
						if (!in_array(addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/'), $old_replace_values)) {
							$old_replace_values[] = addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/');
							$new_replace_values[] = addcslashes(owm_url_scheme(site_url(), $new_schemes[$i]), '/');
						}
					}

					// Add email
					if (!isset($config['NoEmailReplace'])) {
						if (!in_array(sprintf('@%s', $old_domain), $old_replace_values)) {
							$old_replace_values[] = sprintf('@%s', $old_domain);
							$new_replace_values[] = sprintf('@%s', $new_domain);
						}
					}
				}
			}
		}

		$home_urls = array();

		// Add Home URL
		if (!empty($config['HomeURL'])) {
			$home_urls[] = $config['HomeURL'];
		}

		// Add Internal Home URL
		if (!empty($config['InternalHomeURL'])) {
			if (wp_parse_url($config['InternalHomeURL'], PHP_URL_SCHEME) && wp_parse_url($config['InternalHomeURL'], PHP_URL_HOST)) {
				$home_urls[] = $config['InternalHomeURL'];
			}
		}

		// Get Home URL
		foreach ($home_urls as $home_url) {

			// Replace Home URL
			if ($home_url !== home_url()) {

				// Get www URL
				if (stripos($home_url, '//www.') !== false) {
					$home_url_www_inversion = str_ireplace('//www.', '//', $home_url);
				} else {
					$home_url_www_inversion = str_ireplace('//', '//www.', $home_url);
				}

				// Replace Home URL
				foreach (array($home_url, $home_url_www_inversion) as $url) {

					// Get domain
					$old_domain = wp_parse_url($url, PHP_URL_HOST);
					$new_domain = wp_parse_url(home_url(), PHP_URL_HOST);

					// Get path
					$old_path = wp_parse_url($url, PHP_URL_PATH);
					$new_path = wp_parse_url(home_url(), PHP_URL_PATH);

					// Get scheme
					$new_scheme = wp_parse_url(home_url(), PHP_URL_SCHEME);

					// Add domain and path
					if (!in_array(sprintf("'%s','%s'", $old_domain, trailingslashit($old_path)), $old_replace_raw_values)) {
						$old_replace_raw_values[] = sprintf("'%s','%s'", $old_domain, trailingslashit($old_path));
						$new_replace_raw_values[] = sprintf("'%s','%s'", $new_domain, trailingslashit($new_path));
					}

					// Add domain and path with single quote
					if (!in_array(sprintf("='%s%s", $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
						$old_replace_values[] = sprintf("='%s%s", $old_domain, untrailingslashit($old_path));
						$new_replace_values[] = sprintf("='%s%s", $new_domain, untrailingslashit($new_path));
					}

					// Add domain and path with double quote
					if (!in_array(sprintf('="%s%s', $old_domain, untrailingslashit($old_path)), $old_replace_values)) {
						$old_replace_values[] = sprintf('="%s%s', $old_domain, untrailingslashit($old_path));
						$new_replace_values[] = sprintf('="%s%s', $new_domain, untrailingslashit($new_path));
					}

					// Add Home URL scheme
					$old_schemes = array('http', 'https', '');
					$new_schemes = array($new_scheme, $new_scheme, '');

					// Replace Home URL scheme
					for ($i = 0; $i < count($old_schemes); $i++) {

						// Add plain Home URL
						if (!in_array(owm_url_scheme($url, $old_schemes[$i]), $old_replace_values)) {
							$old_replace_values[] = owm_url_scheme($url, $old_schemes[$i]);
							$new_replace_values[] = owm_url_scheme(home_url(), $new_schemes[$i]);
						}

						// Add URL encoded Home URL
						if (!in_array(urlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
							$old_replace_values[] = urlencode(owm_url_scheme($url, $old_schemes[$i]));
							$new_replace_values[] = urlencode(owm_url_scheme(home_url(), $new_schemes[$i]));
						}

						// Add URL raw encoded Home URL
						if (!in_array(rawurlencode(owm_url_scheme($url, $old_schemes[$i])), $old_replace_values)) {
							$old_replace_values[] = rawurlencode(owm_url_scheme($url, $old_schemes[$i]));
							$new_replace_values[] = rawurlencode(owm_url_scheme(home_url(), $new_schemes[$i]));
						}

						// Add JSON escaped Home URL
						if (!in_array(addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/'), $old_replace_values)) {
							$old_replace_values[] = addcslashes(owm_url_scheme($url, $old_schemes[$i]), '/');
							$new_replace_values[] = addcslashes(owm_url_scheme(home_url(), $new_schemes[$i]), '/');
						}
					}

					// Add email
					if (!isset($config['NoEmailReplace'])) {
						if (!in_array(sprintf('@%s', $old_domain), $old_replace_values)) {
							$old_replace_values[] = sprintf('@%s', $old_domain);
							$new_replace_values[] = sprintf('@%s', $new_domain);
						}
					}
				}
			}
		}

		// Get WordPress Content Dir
		if (isset($config['WordPress']['Content']) && ($content_dir = $config['WordPress']['Content'])) {

			// Replace WordPress Content Dir
			if ($content_dir !== WP_CONTENT_DIR) {

				// Add plain WordPress Content
				if (!in_array($content_dir, $old_replace_values)) {
					$old_replace_values[] = $content_dir;
					$new_replace_values[] = WP_CONTENT_DIR;
				}

				// Add URL encoded WordPress Content
				if (!in_array(urlencode($content_dir), $old_replace_values)) {
					$old_replace_values[] = urlencode($content_dir);
					$new_replace_values[] = urlencode(WP_CONTENT_DIR);
				}

				// Add URL raw encoded WordPress Content
				if (!in_array(rawurlencode($content_dir), $old_replace_values)) {
					$old_replace_values[] = rawurlencode($content_dir);
					$new_replace_values[] = rawurlencode(WP_CONTENT_DIR);
				}

				// Add JSON escaped WordPress Content
				if (!in_array(addcslashes($content_dir, '/'), $old_replace_values)) {
					$old_replace_values[] = addcslashes($content_dir, '/');
					$new_replace_values[] = addcslashes(WP_CONTENT_DIR, '/');
				}
			}
		}

		// Get replace old and new values
		if (isset($config['Replace']) && ($replace = $config['Replace'])) {
			for ($i = 0; $i < count($replace['OldValues']); $i++) {
				if (!empty($replace['OldValues'][$i]) && !empty($replace['NewValues'][$i])) {

					// Add plain replace values
					if (!in_array($replace['OldValues'][$i], $old_replace_values)) {
						$old_replace_values[] = $replace['OldValues'][$i];
						$new_replace_values[] = $replace['NewValues'][$i];
					}

					// Add URL encoded replace values
					if (!in_array(urlencode($replace['OldValues'][$i]), $old_replace_values)) {
						$old_replace_values[] = urlencode($replace['OldValues'][$i]);
						$new_replace_values[] = urlencode($replace['NewValues'][$i]);
					}

					// Add URL raw encoded replace values
					if (!in_array(rawurlencode($replace['OldValues'][$i]), $old_replace_values)) {
						$old_replace_values[] = rawurlencode($replace['OldValues'][$i]);
						$new_replace_values[] = rawurlencode($replace['NewValues'][$i]);
					}

					// Add JSON Escaped replace values
					if (!in_array(addcslashes($replace['OldValues'][$i], '/'), $old_replace_values)) {
						$old_replace_values[] = addcslashes($replace['OldValues'][$i], '/');
						$new_replace_values[] = addcslashes($replace['NewValues'][$i], '/');
					}
				}
			}
		}

		// Get site URL
		$site_url = get_option(OWM_SITE_URL);

		// Get home URL
		$home_url = get_option(OWM_HOME_URL);

		// Get secret key
		$secret_key = get_option(OWM_SECRET_KEY);

		// Get HTTP user
		$auth_user = get_option(OWM_AUTH_USER);

		// Get HTTP password
		$auth_password = get_option(OWM_AUTH_PASSWORD);

		$old_table_prefixes = array();
		$new_table_prefixes = array();

		// Set main table prefixes
		$old_table_prefixes[] = owm_prefix('mainsite');
		$new_table_prefixes[] = owm_table_prefix();

		// Set site table prefixes
		foreach ($blogs as $blog) {
			if (owm_main_site($blog['Old']['BlogID']) === false) {
				$old_table_prefixes[] = owm_prefix($blog['Old']['BlogID']);
				$new_table_prefixes[] = owm_table_prefix($blog['New']['BlogID']);
			}
		}

		// Set base table prefixes
		foreach ($blogs as $blog) {
			if (owm_main_site($blog['Old']['BlogID']) === true) {
				$old_table_prefixes[] = owm_prefix('basesite');
				$new_table_prefixes[] = owm_table_prefix($blog['New']['BlogID']);
			}
		}

		// Set site table prefixes
		foreach ($blogs as $blog) {
			if (owm_main_site($blog['Old']['BlogID']) === true) {
				$old_table_prefixes[] = owm_prefix($blog['Old']['BlogID']);
				$new_table_prefixes[] = owm_table_prefix($blog['New']['BlogID']);
			}
		}

		// Set table prefixes
		$old_table_prefixes[] = owm_prefix();
		$new_table_prefixes[] = owm_table_prefix();


		$old_table_prefixes[] = 'owm_PREFIX_mainsite_';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'owm_PREFIX_basesite_';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'owm_PREFIX_mainsite';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'OWM_PREFIX_basesite';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'OWM_PREFIX_';
		$new_table_prefixes[] = owm_table_prefix();

		// Compatibility with Onward / Omini / All In One WP Migration backups
		$old_table_prefixes[] = 'ONWARD_PREFIX_mainsite_';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'ONWARD_PREFIX_basesite_';
		$new_table_prefixes[] = owm_table_prefix();
		foreach ($blogs as $blog) {
			if (owm_main_site($blog['Old']['BlogID']) === false) {
				$old_table_prefixes[] = 'ONWARD_PREFIX_' . $blog['Old']['BlogID'] . '_';
				$new_table_prefixes[] = owm_table_prefix($blog['New']['BlogID']);
			} else {
				$old_table_prefixes[] = 'ONWARD_PREFIX_' . $blog['Old']['BlogID'] . '_';
				$new_table_prefixes[] = owm_table_prefix($blog['New']['BlogID']);
			}
		}
		$old_table_prefixes[] = 'ONWARD_PREFIX_mainsite';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'ONWARD_PREFIX_basesite';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'ONWARD_PREFIX_';
		$new_table_prefixes[] = owm_table_prefix();

		$old_table_prefixes[] = 'OMINI_PREFIX_mainsite_';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'OMINI_PREFIX_basesite_';
		$new_table_prefixes[] = owm_table_prefix();
		foreach ($blogs as $blog) {
			if (owm_main_site($blog['Old']['BlogID']) === false) {
				$old_table_prefixes[] = 'OMINI_PREFIX_' . $blog['Old']['BlogID'] . '_';
				$new_table_prefixes[] = owm_table_prefix($blog['New']['BlogID']);
			} else {
				$old_table_prefixes[] = 'OMINI_PREFIX_' . $blog['Old']['BlogID'] . '_';
				$new_table_prefixes[] = owm_table_prefix($blog['New']['BlogID']);
			}
		}
		$old_table_prefixes[] = 'OMINI_PREFIX_mainsite';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'OMINI_PREFIX_basesite';
		$new_table_prefixes[] = owm_table_prefix();
		$old_table_prefixes[] = 'OMINI_PREFIX_';
		$new_table_prefixes[] = owm_table_prefix();

		// Auto-detect table prefix from SQL dump only if exported with a raw table prefix (not OWM_PREFIX_ / ONWARD_PREFIX_ / OMINI_PREFIX_)
		$db_path = owm_database_path($params);
		if (is_file($db_path)) {
			$sample = file_get_contents($db_path, false, null, 0, 16384);
			if ($sample && preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([a-zA-Z0-9_]+_)(?:options|posts|users)`/i', $sample, $matches)) {
				$detected_prefix = $matches[1];
				if (
					stripos($detected_prefix, 'OWM_PREFIX_') === false &&
					stripos($detected_prefix, 'ONWARD_PREFIX_') === false &&
					stripos($detected_prefix, 'OMINI_PREFIX_') === false &&
					!in_array($detected_prefix, $old_table_prefixes) &&
					$detected_prefix !== owm_table_prefix()
				) {
					$old_table_prefixes[] = $detected_prefix;
					$new_table_prefixes[] = owm_table_prefix();
				}
			}
		}

		// Get database client
		if (empty($wpdb->use_mysqli)) {
			$mysql = new Owm_Database_Mysql($wpdb);
		} else {
			$mysql = new Owm_Database_Mysqli($wpdb);
		}

		// Set database options
		$mysql->set_old_table_prefixes($old_table_prefixes)
			->set_new_table_prefixes($new_table_prefixes)
			->set_old_replace_values($old_replace_values)
			->set_new_replace_values($new_replace_values)
			->set_old_replace_raw_values($old_replace_raw_values)
			->set_new_replace_raw_values($new_replace_raw_values);

		// Note: Do NOT call $mysql->flush() here. Every table in database.sql includes its own
		// DROP TABLE IF EXISTS statement right before CREATE TABLE, and wp_options/wp_users/wp_usermeta
		// are imported via shadow tables and swapped atomically at EOF so key WordPress tables are never missing.
		$mysql->set_atomic_tables(array());

		// Set Visual Composer
		$mysql->set_visual_composer(owm_validate_plugin_basename('js_composer/js_composer.php'));

		// Set BeTheme Responsive
		$mysql->set_betheme_responsive(owm_validate_theme_basename('betheme/style.css'));

		// Import database
		if ($mysql->import(owm_database_path($params), $query_offset)) {

			// Set progress
			Owm_Status::info(__('Done restoring database.', 'onward-web-migration'));

			// Unset query offset
			unset($params['query_offset']);

			// Unset total queries size
			unset($params['total_queries_size']);

			// Unset completed flag
			unset($params['completed']);

		} else {

			// Get total queries size
			$total_queries_size = owm_database_bytes($params);

			// What percent of queries have we processed?
			$progress = (int) (($query_offset / $total_queries_size) * 100);

			// Set progress
			/* translators: %d: progress percentage */
			Owm_Status::info(sprintf(__('Restoring database...<br />%d%% complete', 'onward-web-migration'), $progress));

			// Set query offset
			$params['query_offset'] = $query_offset;

			// Set total queries size
			$params['total_queries_size'] = $total_queries_size;

			// Set completed flag
			$params['completed'] = false;
		}

		// Flush WP cache
		owm_cache_flush();

		// Activate plugins
		owm_activate_plugins(owm_active_owm_plugins());

		// Set the new site URL
		update_option(OWM_SITE_URL, $site_url);

		// Set the new home URL
		update_option(OWM_HOME_URL, $home_url);

		// Set the new secret key value
		update_option(OWM_SECRET_KEY, $secret_key);

		// Set the new HTTP user
		update_option(OWM_AUTH_USER, $auth_user);

		// Set the new HTTP password
		update_option(OWM_AUTH_PASSWORD, $auth_password);

		return $params;
	}
}
