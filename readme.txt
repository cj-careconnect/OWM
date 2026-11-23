=== Onward Web Migration ===
Contributors: chathuJ
Donate link: 
Tags: migration, backup, export, import, transfer
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Migration tool for all your website data. Import or Export your website content with just a click.

== Description ==

Onward Web Migration is a migration tool for WordPress that allows you to export and import your website content with ease.

= Features =

* **Unlimited Import Size**: Import or restore backups without size limitations, providing flexibility to migrate websites of any scale.
* **Seamless Migration**: Export your WordPress site, including the database, media files, plugins, and themes, and import it into any location within WordPress.
* **Drag-and-Drop Functionality**: Effortlessly upload and import your site backups using the WordPress admin interface.
* **Multisite Support**: Compatible with single and multisite WordPress networks.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Access Onward Web Migration from the admin menu to export or import your site data.

== Frequently Asked Questions ==

= Is there any import file size limit? =

No, Onward Web Migration allows you to import backups without any size restrictions.

= Which PHP versions are supported? =

PHP 7.4 or higher is recommended.

== Source Code & Build Instructions ==

Onward Web Migration is free, open-source software licensed under the GNU General Public License v2 or later.

The complete unminified source code, build configuration, and assets are publicly accessible at:
https://github.com/cj-careconnect/OWM

= JavaScript Assets & Build Tools =
* `unified-workspace.js`: Primary unminified, human-readable controller script for the modern single-page migration workspace interface.
* `browser-zip.js`: Unminified, human-readable client-side streaming ZIP engine utilizing browser Web Workers and DecompressionStream/CompressionStream.
* `util.min.js`, `import.min.js`, `export.min.js`, `backups.min.js`: Modular JavaScript components bundled using Webpack. Source modules and build configurations can be inspected and compiled using standard Webpack:
  1. Clone the repository: `git clone https://github.com/cj-careconnect/OWM
  2. Install dependencies: `npm install`
  3. Run build: `npm run build`

== Third-Party Libraries ==

This plugin bundles the following open-source third-party libraries:
* **Math_BigInteger** (`BigInteger.php`): Pure-PHP arbitrary precision integer arithmetic library from phpseclib by TerraFrost. License: MIT. Repository: https://github.com/phpseclib/phpseclib
* **Bandar** (`Bandar.php`): Minimal PHP template renderer. License: MIT. Repository: https://github.com/bandar/bandar
* **Browser ZIP Engine** (`browser-zip.js`): Browser-based client-side streaming ZIP archive handler. License: MIT / GPLv2+.

== Screenshots ==

== Changelog ==

= 1.0.7 =
* Security: Strictly enforce nonce verification and user capability checks on all AJAX endpoints (list_backups, delete, status, upload, flush_permalinks).
* Security: Removed secret-key bypass for authenticated users across all controller endpoints.
* Security: Enforce unguessable 32-character random tokens for generated backup archive filenames.
* Security: Added directory deny rules (.htaccess with Require all denied, web.config, and index.php) to storage and backup directories.
* Security: Sanitize and strip secret keys, nonces, and authentication credentials from all operational logs.
* Security: Strictly validate archive filenames during import upload to only permit .zip/.wpress extensions and prevent arbitrary PHP script insertion.
* Security: Disabled arbitrary PHP execution via mu-plugins extraction during archive restoration.
* Security: Removed arbitrary custom CSS injection from package metadata (relying on native WordPress database post records).
* Compatibility: Dynamically support external WP_PLUGIN_DIR and wp_upload_dir() locations configured outside WP_CONTENT_DIR.
* Documentation: Documented source code availability, build steps, and third-party libraries.

= 1.0.6 =
* Minor UI upgrade.

= 1.0.5 =
* Renamed plugin to Onward Web Migration and updated plugin slug to onward-web-migration.
* Updated text domain to match plugin slug for WordPress.org compliance.

= 1.0.3 =
* Automatically deactivate pre-existing old plugins and keep only restored backup plugins active.
* Preserve exact original post, page, product, and content publication status as per the backup site.

= 1.0.2 =
* UI design enhancements, improved contrast accessibility, and view navigation refinements.

= 1.0.1 =
* Added failsafe for larger DB imports

= 1.0.0 =
* Initial release.
* Export themes files.
* Export installed plugins.
* Unlimited import support.

== Upgrade Notice ==

= 1.0.7 =
Security enhancements for nonces, permissions, directory protection, and archive handling.

= 1.0.0 =
Initial release.
