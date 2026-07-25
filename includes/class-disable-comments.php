<?php
/**
 * 全局禁用评论。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 关闭评论支持、入口、REST 与 pingback。
 */
class WPSE_Disable_Comments {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public function register() {
		$settings = WPSE_Settings::get();
		if ( empty( $settings['enable_disable_comments'] ) ) {
			return;
		}

		add_action( 'init', array( $this, 'on_init' ), 10 );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
		add_filter( 'rest_endpoints', array( $this, 'filter_rest_comments' ) );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );
		add_action( 'admin_menu', array( $this, 'remove_admin_menus' ) );
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_admin_bar_comments' ) );
		add_filter( 'xmlrpc_methods', array( $this, 'filter_xmlrpc_methods' ) );
	}

	/**
	 * 拦截评论提交并移除文章类型评论支持。
	 *
	 * @return void
	 */
	public function on_init() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false !== strpos( $request_uri, 'wp-comments-post.php' ) ) {
			wp_die(
				esc_html__( '本站已关闭评论功能。', 'wp-site-essentials' ),
				esc_html__( '评论已关闭', 'wp-site-essentials' ),
				array( 'response' => 403 )
			);
		}

		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * 重定向评论相关后台页并移除仪表盘小工具。
	 *
	 * @return void
	 */
	public function on_admin_init() {
		global $pagenow;
		if ( 'edit-comments.php' === $pagenow || 'options-discussion.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * 移除评论 REST 端点。
	 *
	 * @param array<string, mixed> $endpoints 端点列表。
	 * @return array<string, mixed>
	 */
	public function filter_rest_comments( $endpoints ) {
		unset( $endpoints['/wp/v2/comments'], $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
		return $endpoints;
	}

	/**
	 * 移除后台评论菜单。
	 *
	 * @return void
	 */
	public function remove_admin_menus() {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * 移除管理栏评论图标。
	 *
	 * @return void
	 */
	public function remove_admin_bar_comments() {
		global $wp_admin_bar;
		if ( $wp_admin_bar instanceof WP_Admin_Bar ) {
			$wp_admin_bar->remove_menu( 'comments' );
		}
	}

	/**
	 * 禁用 XML-RPC pingback。
	 *
	 * @param array<string, mixed> $methods 方法列表。
	 * @return array<string, mixed>
	 */
	public function filter_xmlrpc_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}
}
