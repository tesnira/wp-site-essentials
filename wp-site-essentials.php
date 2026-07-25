<?php
/**
 * Plugin Name: WP Site Essentials
 * Plugin URI: https://wordpress.org/plugins/wp-site-essentials/
 * Description: 站点基础能力：URL 小写重定向、敏感 REST 隐身、全局禁评与站点清理工具。
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: biancaplus
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-site-essentials
 * Domain Path: /languages
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSE_VERSION', '1.0.0' );
define( 'WPSE_FILE', __FILE__ );
define( 'WPSE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPSE_URL', plugin_dir_url( __FILE__ ) );

require_once WPSE_PATH . 'includes/class-settings.php';
require_once WPSE_PATH . 'includes/class-url-lowercase.php';
require_once WPSE_PATH . 'includes/class-rest-hardening.php';
require_once WPSE_PATH . 'includes/class-disable-comments.php';
require_once WPSE_PATH . 'includes/class-cleanup.php';
require_once WPSE_PATH . 'includes/admin/class-settings-page.php';
require_once WPSE_PATH . 'includes/class-plugin.php';

/**
 * 启动插件。
 *
 * @return void
 */
function wpse_run_plugin() {
	$plugin = new WPSE_Plugin();
	$plugin->run();
}

wpse_run_plugin();
