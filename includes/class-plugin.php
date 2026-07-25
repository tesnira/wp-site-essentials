<?php
/**
 * 插件主控制器。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 组装并启动各模块。
 */
class WPSE_Plugin {

	/**
	 * 注册插件功能。
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		$settings_page = new WPSE_Settings_Page();
		$url           = new WPSE_Url_Lowercase();
		$rest          = new WPSE_Rest_Hardening();
		$comments      = new WPSE_Disable_Comments();
		$cleanup       = new WPSE_Cleanup();

		$settings_page->register();
		$url->register();
		$rest->register();
		$comments->register();
		$cleanup->register();
	}

	/**
	 * 加载翻译。
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-site-essentials', false, dirname( plugin_basename( WPSE_FILE ) ) . '/languages' );
	}
}
