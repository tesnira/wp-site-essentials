<?php
/**
 * 敏感 REST 接口隐身。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 未登录用户隐藏配置的 REST 路由。
 */
class WPSE_Rest_Hardening {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public function register() {
		$settings = WPSE_Settings::get();
		if ( empty( $settings['enable_rest_hardening'] ) ) {
			return;
		}
		add_filter( 'rest_endpoints', array( $this, 'filter_endpoints' ) );
	}

	/**
	 * 从端点表中移除黑名单路由。
	 *
	 * @param array<string, mixed> $endpoints 端点列表。
	 * @return array<string, mixed>
	 */
	public function filter_endpoints( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		$blacklist = WPSE_Settings::get_active_rest_routes();
		if ( empty( $blacklist ) ) {
			return $endpoints;
		}

		foreach ( $blacklist as $route ) {
			if ( isset( $endpoints[ $route ] ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}
}
