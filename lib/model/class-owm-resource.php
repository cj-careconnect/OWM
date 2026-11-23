<?php
/**
 * Onward Web Migration - resource governor
 *
 * @package Onward_Web_Migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Owm_Resource {

	/** Option name where benchmark result is cached */
	const OPTION = 'owm_resource_profile';

	/** Re-benchmark interval */
	const TTL = 86400;

	/** Size of a deflate "segment" (full flush / resume point interval) */
	const SEGMENT_SIZE = 8388608;

	/**
	 * Cached profile for current request
	 *
	 * @var array|null
	 */
	protected static $profile = null;

	/**
	 * Extensions that are already compressed: deflating them burns CPU for ~0% gain
	 *
	 * @var array
	 */
	protected static $store_extensions = array(
		// images
		'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'jxl', 'ico',
		// audio / video
		'mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'flac', 'wma', 'mp4', 'm4v', 'mov', 'avi',
		'mkv', 'webm', 'wmv', 'flv', '3gp', 'mpg', 'mpeg',
		// archives
		'zip', 'gz', 'tgz', 'bz2', 'xz', 'lz', 'lzma', '7z', 'rar', 'zst', 'br', 'wpress', 'jar',
		// fonts
		'woff', 'woff2',
		// documents (internally compressed)
		'pdf', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'epub', 'apk',
	);

	/**
	 * Get server resource profile
	 *
	 * @return array (name, mbps, memory, max_execution_time)
	 */
	public static function profile() {
		if ( null !== self::$profile ) {
			return self::$profile;
		}

		$cached = function_exists( 'get_option' ) ? get_option( self::OPTION ) : false;
		if ( is_array( $cached ) && isset( $cached['name'], $cached['time'] ) && ( time() - $cached['time'] ) < self::TTL ) {
			$profile = $cached;
		} else {
			$profile = self::benchmark();
			if ( function_exists( 'update_option' ) ) {
				update_option( self::OPTION, $profile, false );
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$profile['name'] = apply_filters( 'owm_resource_profile', $profile['name'], $profile );
		}

		return self::$profile = $profile;
	}

	/**
	 * Get profile name
	 *
	 * @return string
	 */
	public static function profile_name() {
		$profile = self::profile();
		return $profile['name'];
	}

	/**
	 * Is this a low resource server?
	 *
	 * @return bool
	 */
	public static function is_low() {
		return in_array( self::profile_name(), array( 'low', 'minimal' ), true );
	}

	/**
	 * Measure deflate + crc throughput on a 1 MB sample
	 *
	 * @return array
	 */
	public static function benchmark() {
		$memory = self::memory_limit();
		$max    = (int) @ini_get( 'max_execution_time' );
		$mbps   = 0;

		if ( function_exists( 'gzdeflate' ) ) {
			// Semi-compressible sample, similar to SQL / PHP / CSS text
			$sample = '';
			$i      = 0;
			while ( strlen( $sample ) < 1048576 ) {
				$sample .= "INSERT INTO `wp_posts` VALUES ({$i},'" . md5( $i ) . "','lorem ipsum dolor sit amet " . ( $i * 7919 ) . "');\n";
				$i++;
			}

			$start = microtime( true );
			gzdeflate( $sample, 1 );
			hash( 'crc32b', $sample );
			$elapsed = max( microtime( true ) - $start, 0.0001 );
			$mbps    = round( ( strlen( $sample ) / 1048576 ) / $elapsed, 1 );
		}

		if ( ! function_exists( 'deflate_init' ) ) {
			$name = 'minimal';
		} elseif ( $mbps >= 80 ) {
			$name = 'high';
		} elseif ( $mbps >= 30 ) {
			$name = 'normal';
		} elseif ( $mbps >= 8 ) {
			$name = 'low';
		} else {
			$name = 'minimal';
		}

		// Tight memory or very short execution time: step down one level
		if ( ( $memory > 0 && $memory < 67108864 ) || ( $max > 0 && $max < 20 ) ) {
			$steps = array( 'high' => 'normal', 'normal' => 'low', 'low' => 'minimal', 'minimal' => 'minimal' );
			$name  = $steps[ $name ];
		}

		return array(
			'name'               => $name,
			'mbps'               => $mbps,
			'memory'             => $memory,
			'max_execution_time' => $max,
			'time'               => time(),
		);
	}

	/**
	 * Compression level for this request (0 = store only)
	 *
	 * @param  array $params Request parameters
	 * @return int
	 */
	public static function compression_level( $params = array() ) {
		if ( ! function_exists( 'deflate_init' ) ) {
			return 0;
		}

		// Browser will compress the archive while downloading it
		if ( ! empty( $params['options']['browser_compress'] ) ) {
			$level = 0;
		} else {
			$levels = array( 'high' => 5, 'normal' => 3, 'low' => 1, 'minimal' => 0 );
			$name   = self::profile_name();
			$level  = isset( $levels[ $name ] ) ? $levels[ $name ] : 1;
		}

		if ( function_exists( 'apply_filters' ) ) {
			$level = (int) apply_filters( 'owm_compression_level', $level, $params );
		}

		return max( 0, min( 9, $level ) );
	}

	/**
	 * Should this entry be deflated?
	 *
	 * @param  string $name  Entry name
	 * @param  int    $size  Entry size
	 * @param  int    $level Compression level
	 * @return bool
	 */
	public static function should_compress( $name, $size, $level ) {
		if ( $level <= 0 || $size < 256 ) {
			return false;
		}

		$extensions = self::$store_extensions;
		if ( function_exists( 'apply_filters' ) ) {
			$extensions = apply_filters( 'owm_store_extensions', $extensions );
		}

		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		return ! in_array( $extension, $extensions, true );
	}

	/**
	 * I/O chunk size based on memory limit
	 *
	 * @return int
	 */
	public static function chunk_size() {
		$memory = self::memory_limit();

		if ( $memory > 0 && $memory < 67108864 ) {
			$size = 131072; // 128 KB
		} elseif ( $memory > 0 && $memory < 268435456 ) {
			$size = 524288; // 512 KB
		} else {
			$size = 1048576; // 1 MB
		}

		if ( function_exists( 'apply_filters' ) ) {
			$size = (int) apply_filters( 'owm_io_chunk_size', $size );
		}

		return max( 8192, $size );
	}

	/**
	 * Per-request time budget (seconds) for archive steps
	 *
	 * Hooked into "owm_completed_timeout" at an early priority, so WP-CLI
	 * (which sets it to 0 later) and user filters still win.
	 *
	 * @param  int $timeout Default timeout
	 * @return int|float
	 */
	public static function timeout( $timeout = 10 ) {
		if ( (int) $timeout !== 10 ) {
			return $timeout;
		}

		$max = (int) @ini_get( 'max_execution_time' );
		if ( $max <= 0 ) {
			return 10;
		}

		// Leave plenty of head room for bootstrap + response on slow servers
		return max( 2, min( 10, floor( $max * 0.4 ) ) );
	}

	/**
	 * CPU duty cycle (1 = full speed, 0.75 = rest 25% of the time)
	 *
	 * @return float
	 */
	public static function duty_cycle() {
		$cycles = array( 'high' => 1.0, 'normal' => 1.0, 'low' => 0.85, 'minimal' => 0.75 );
		$name   = self::profile_name();
		$duty   = isset( $cycles[ $name ] ) ? $cycles[ $name ] : 1.0;

		if ( function_exists( 'apply_filters' ) ) {
			$duty = (float) apply_filters( 'owm_cpu_duty_cycle', $duty );
		}

		return max( 0.1, min( 1.0, $duty ) );
	}

	/**
	 * Rest proportionally to the work just done (CPU duty-cycle limiter)
	 *
	 * @param  float $work_seconds Busy time since last call
	 * @return void
	 */
	public static function breathe( $work_seconds ) {
		static $duty = null;
		if ( null === $duty ) {
			$duty = self::duty_cycle();
		}

		if ( $duty < 1.0 && $work_seconds > 0 ) {
			$rest = $work_seconds * ( 1 - $duty ) / $duty;
			if ( $rest > 0.0005 ) {
				usleep( (int) min( 250000, $rest * 1000000 ) );
			}
		}
	}

	/**
	 * Memory limit in bytes (0 = unlimited)
	 *
	 * @return int
	 */
	public static function memory_limit() {
		$limit = trim( (string) @ini_get( 'memory_limit' ) );
		if ( '' === $limit || '-1' === $limit ) {
			return 0;
		}

		$unit  = strtolower( substr( $limit, -1 ) );
		$value = (int) $limit;
		switch ( $unit ) {
			case 'g':
				$value *= 1024;
				// no break
			case 'm':
				$value *= 1024;
				// no break
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Data passed to the browser so it can decide to take over heavy work
	 *
	 * @return array
	 */
	public static function client_config() {
		$profile = self::profile();

		return array(
			'profile'         => $profile['name'],
			'mbps'            => $profile['mbps'],
			'prefer_browser'  => self::is_low(),
			'zlib'            => function_exists( 'deflate_init' ),
			'max_upload_size' => function_exists( 'wp_max_upload_size' ) ? wp_max_upload_size() : 0,
		);
	}
}
