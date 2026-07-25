<?php
/**
 * 大写 URL 重定向为小写。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 前台 GET/HEAD 将 path 转为小写并 301。
 */
class WPSE_Url_Lowercase {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public function register() {
		$settings = WPSE_Settings::get();
		if ( empty( $settings['enable_url_lowercase'] ) ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * 检测并重定向大写 path。
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return;
		}

		$requested_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( '' === $requested_uri ) {
			return;
		}

		$parts = wp_parse_url( $requested_uri );
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$query = is_array( $parts ) && isset( $parts['query'] ) ? (string) $parts['query'] : '';

		if ( '' !== $path ) {
			if ( 0 === strpos( $path, '/wp-content/' ) || 0 === strpos( $path, '/wp-includes/' ) || 0 === strpos( $path, '/wp-admin/' ) ) {
				return;
			}
			$basename = basename( $path );
			if ( false !== strpos( $basename, '.' ) ) {
				return;
			}
		}

		$lower_path = strtolower( $path );
		if ( $lower_path === $path ) {
			return;
		}

		$target_uri = $lower_path . ( '' !== $query ? ( '?' . $query ) : '' );
		if ( $target_uri === $requested_uri ) {
			return;
		}

		wp_safe_redirect( home_url( $target_uri ), 301 );
		exit;
	}
}
