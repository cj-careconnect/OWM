<?php
/**
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('OWM_PATH')) {
	define('OWM_PATH', dirname(__FILE__));
}

// ================
// = Plugin Debug =
// ================
define('OWM_DEBUG', false);

// ==================
// = Plugin Version =
// ==================
define('OWM_VERSION', '1.0.7');

// ===============
// = Plugin Name =
// ===============
define('OWM_PLUGIN_NAME', 'onward-web-migration');

// ============================
// = Directory index.php File =
// ============================
define('OWM_DIRECTORY_INDEX', 'index.php');

// ================
// = Storage Path =
// ================
define('OWM_STORAGE_PATH', OWM_PATH . DIRECTORY_SEPARATOR . 'storage');

// ==================
// = Error Log Path =
// ==================
define('OWM_ERROR_FILE', OWM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'error.log');

// ===============
// = Status Path =
// ===============
define('OWM_STATUS_FILE', OWM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'status.js');

// ============
// = Lib Path =
// ============
define('OWM_LIB_PATH', OWM_PATH . DIRECTORY_SEPARATOR . 'lib');

// ===================
// = Controller Path =
// ===================
define('OWM_CONTROLLER_PATH', OWM_LIB_PATH . DIRECTORY_SEPARATOR . 'controller');

// ==============
// = Model Path =
// ==============
define('OWM_MODEL_PATH', OWM_LIB_PATH . DIRECTORY_SEPARATOR . 'model');

// ===============
// = Export Path =
// ===============
define('OWM_EXPORT_PATH', OWM_MODEL_PATH . DIRECTORY_SEPARATOR . 'export');

// ===============
// = Import Path =
// ===============
define('OWM_IMPORT_PATH', OWM_MODEL_PATH . DIRECTORY_SEPARATOR . 'import');

// =============
// = View Path =
// =============
define('OWM_TEMPLATES_PATH', OWM_LIB_PATH . DIRECTORY_SEPARATOR . 'view');

// ===================
// = Set Bandar Path =
// ===================
define('BANDAR_TEMPLATES_PATH', OWM_TEMPLATES_PATH);

// ===============
// = Vendor Path =
// ===============
define('OWM_VENDOR_PATH', OWM_LIB_PATH . DIRECTORY_SEPARATOR . 'vendor');

// =========================
// = OWM Archive Tools Url =
// =========================
define('OWM_ARCHIVE_TOOLS_URL', '');

// ====================
// = OWM Table Prefix =
// ====================
define('OWM_TABLE_PREFIX', 'OWM_PREFIX_');

// ========================
// = Archive Backups Name =
// ========================
define('OWM_BACKUPS_NAME', 'owm-backups');

// =========================
// = Archive Database Name =
// =========================
define('OWM_DATABASE_NAME', 'database.sql');

// ========================
// = Archive Package Name =
// ========================
define('OWM_PACKAGE_NAME', 'package.json');

// ==========================
// = Archive Multisite Name =
// ==========================
define('OWM_MULTISITE_NAME', 'multisite.json');

// ======================
// = Archive Blogs Name =
// ======================
define('OWM_BLOGS_NAME', 'blogs.json');

// =========================
// = Archive Settings Name =
// =========================
define('OWM_SETTINGS_NAME', 'settings.json');

// ==========================
// = Archive Multipart Name =
// ==========================
define('OWM_MULTIPART_NAME', 'multipart.list');

// ========================
// = Archive Filemap Name =
// ========================
define('OWM_FILEMAP_NAME', 'filemap.list');

// =================================
// = Archive Must-Use Plugins Name =
// =================================
define('OWM_MUPLUGINS_NAME', 'mu-plugins');

// =============================
// = Endurance Page Cache Name =
// =============================
define('OWM_ENDURANCE_PAGE_CACHE_NAME', 'endurance-page-cache.php');

// ===========================
// = Endurance PHP Edge Name =
// ===========================
define('OWM_ENDURANCE_PHP_EDGE_NAME', 'endurance-php-edge.php');

// ================================
// = Endurance Browser Cache Name =
// ================================
define('OWM_ENDURANCE_BROWSER_CACHE_NAME', 'endurance-browser-cache.php');

// =========================
// = GD System Plugin Name =
// =========================
define('OWM_GD_SYSTEM_PLUGIN_NAME', 'gd-system-plugin.php');

// ===================
// = Export Log Name =
// ===================
define('OWM_EXPORT_NAME', 'export.log');

// ===================
// = Import Log Name =
// ===================
define('OWM_IMPORT_NAME', 'import.log');

// ==================
// = Error Log Name =
// ==================
define('OWM_ERROR_NAME', 'error.log');

// ==============
// = Secret Key =
// ==============
define('OWM_SECRET_KEY', 'owm_secret_key');

// =============
// = Auth User =
// =============
define('OWM_AUTH_USER', 'owm_auth_user');

// =================
// = Auth Password =
// =================
define('OWM_AUTH_PASSWORD', 'owm_auth_password');

// ============
// = Site URL =
// ============
define('OWM_SITE_URL', 'siteurl');

// ============
// = Home URL =
// ============
define('OWM_HOME_URL', 'home');

// ==================
// = Active Plugins =
// ==================
define('OWM_ACTIVE_PLUGINS', 'active_plugins');

// ===========================
// = Active Sitewide Plugins =
// ===========================
define('OWM_ACTIVE_SITEWIDE_PLUGINS', 'active_sitewide_plugins');

// ==========================
// = Jetpack Active Modules =
// ==========================
define('OWM_JETPACK_ACTIVE_MODULES', 'jetpack_active_modules');

// ======================
// = MS Files Rewriting =
// ======================
define('OWM_MS_FILES_REWRITING', 'ms_files_rewriting');

// ===================
// = Active Template =
// ===================
define('OWM_ACTIVE_TEMPLATE', 'template');

// =====================
// = Active Stylesheet =
// =====================
define('OWM_ACTIVE_STYLESHEET', 'stylesheet');

// ============
// = Cron Key =
// ============
define('OWM_CRON', 'cron');

// ===============
// = Updater Key =
// ===============
define('OWM_UPDATER', 'owm_updater');

// ==============
// = Status Key =
// ==============
define('OWM_STATUS', 'owm_status');

// ================
// = Messages Key =
// ================
define('OWM_MESSAGES', 'owm_messages');

// =================
// = Support Email =
// =================
define('OWM_SUPPORT_EMAIL', '');

// =================
// = Max File Size =
// =================
define('OWM_MAX_FILE_SIZE', 2 << 28 * 1024);

// ==================
// = Max Chunk Size =
// ==================
define('OWM_MAX_CHUNK_SIZE', 5 * 1024 * 1024);

// =====================
// = Max Chunk Retries =
// =====================
define('OWM_MAX_CHUNK_RETRIES', 10);

// ===========================
// = WP_CONTENT_DIR Constant =
// ===========================
if (!defined('WP_CONTENT_DIR')) {
	define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

// ================
// = Uploads Path =
// ================
define('OWM_UPLOADS_PATH', 'uploads');

// ==============
// = Blogs Path =
// ==============
define('OWM_BLOGSDIR_PATH', 'blogs.dir');

// ==============
// = Sites Path =
// ==============
define('OWM_SITES_PATH', OWM_UPLOADS_PATH . DIRECTORY_SEPARATOR . 'sites');

// ================
// = Backups Path =
// ================
define('OWM_BACKUPS_PATH', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'owm-backups');

// ==========================
// = Storage index.php File =
// ==========================
define('OWM_STORAGE_INDEX', OWM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'index.php');

// ==========================
// = Storage .htaccess File =
// ==========================
define('OWM_STORAGE_HTACCESS', OWM_STORAGE_PATH . DIRECTORY_SEPARATOR . '.htaccess');

// ===========================
// = Storage web.config File =
// ===========================
define('OWM_STORAGE_WEBCONFIG', OWM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'web.config');

// ==========================
// = Backups index.php File =
// ==========================
define('OWM_BACKUPS_INDEX', OWM_BACKUPS_PATH . DIRECTORY_SEPARATOR . 'index.php');

// ==========================
// = Backups .htaccess File =
// ==========================
define('OWM_BACKUPS_HTACCESS', OWM_BACKUPS_PATH . DIRECTORY_SEPARATOR . '.htaccess');

// ===========================
// = Backups web.config File =
// ===========================
define('OWM_BACKUPS_WEBCONFIG', OWM_BACKUPS_PATH . DIRECTORY_SEPARATOR . 'web.config');

// ============================
// = WordPress .htaccess File =
// ============================
define('OWM_WORDPRESS_HTACCESS', ABSPATH . DIRECTORY_SEPARATOR . '.htaccess');

// ================================
// = WP Migration Plugin Base Dir =
// ================================
if (defined('OWM_PLUGIN_BASENAME')) {
	define('OWM_PLUGIN_BASEDIR', dirname(OWM_PLUGIN_BASENAME));
} else {
	define('OWM_PLUGIN_BASEDIR', 'onward-web-migration');
}

