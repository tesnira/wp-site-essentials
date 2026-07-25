<?php
/**
 * 插件选项读写。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理站点基础设置。
 */
class WPSE_Settings {

	const OPTION = 'wpse_settings';

	/**
	 * 默认设置（安全默认：策略类功能关闭）。
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enable_url_lowercase'     => 0,
			'enable_rest_hardening'    => 0,
			'rest_preset_routes'       => array(),
			'rest_custom_routes'       => '',
			'enable_disable_comments'  => 0,
			'cleanup_post_types'       => array( 'post', 'page' ),
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * 预置可隐藏的 REST 路由（路由 => 说明）。
	 *
	 * @return array<string, string>
	 */
	public static function rest_preset_routes() {
		return array(
			'/wp/v2/users'                   => __( '用户列表', 'wp-site-essentials' ),
			'/wp/v2/users/(?P<id>[\\d]+)'     => __( '单个用户', 'wp-site-essentials' ),
			'/wp/v2/settings'                => __( '站点设置', 'wp-site-essentials' ),
			'/wp/v2/themes'                  => __( '主题列表', 'wp-site-essentials' ),
			'/wp/v2/plugins'                 => __( '插件列表', 'wp-site-essentials' ),
		);
	}

	/**
	 * 读取设置。
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$settings = array_merge( self::defaults(), $stored );
		$settings['rest_preset_routes'] = self::sanitize_preset_routes(
			isset( $settings['rest_preset_routes'] ) ? $settings['rest_preset_routes'] : array()
		);
		$settings['rest_custom_routes'] = self::normalize_routes_text(
			isset( $settings['rest_custom_routes'] ) ? $settings['rest_custom_routes'] : ''
		);
		$settings['cleanup_post_types'] = self::sanitize_post_types(
			isset( $settings['cleanup_post_types'] ) ? $settings['cleanup_post_types'] : array( 'post', 'page' )
		);
		return $settings;
	}

	/**
	 * 保存设置。
	 *
	 * @param array<string, mixed> $input 表单输入。
	 * @return array<string, mixed>
	 */
	public static function update( $input ) {
		$current = self::get();
		$clean   = array(
			'enable_url_lowercase'     => empty( $input['enable_url_lowercase'] ) ? 0 : 1,
			'enable_rest_hardening'    => empty( $input['enable_rest_hardening'] ) ? 0 : 1,
			'rest_preset_routes'       => self::sanitize_preset_routes(
				isset( $input['rest_preset_routes'] ) ? $input['rest_preset_routes'] : array()
			),
			'rest_custom_routes'       => self::normalize_routes_text(
				isset( $input['rest_custom_routes'] ) ? $input['rest_custom_routes'] : ''
			),
			'enable_disable_comments'  => empty( $input['enable_disable_comments'] ) ? 0 : 1,
			// 文章类型默认勾选改由清理工具单独保存，此处保留原值。
			'cleanup_post_types'       => isset( $current['cleanup_post_types'] )
				? self::sanitize_post_types( $current['cleanup_post_types'] )
				: array( 'post', 'page' ),
			'delete_data_on_uninstall' => empty( $input['delete_data_on_uninstall'] ) ? 0 : 1,
		);
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	/**
	 * 仅更新清理用的默认文章类型勾选。
	 *
	 * @param mixed $types 类型列表。
	 * @return string[]
	 */
	public static function update_cleanup_post_types( $types ) {
		$settings                       = self::get();
		$settings['cleanup_post_types'] = self::sanitize_post_types( $types );
		update_option( self::OPTION, $settings, false );
		return $settings['cleanup_post_types'];
	}

	/**
	 * 清洗预置路由勾选。
	 *
	 * @param mixed $routes 原始值。
	 * @return string[]
	 */
	public static function sanitize_preset_routes( $routes ) {
		if ( ! is_array( $routes ) ) {
			return array();
		}
		$allowed = array_keys( self::rest_preset_routes() );
		$out     = array();
		foreach ( $routes as $route ) {
			$route = (string) $route;
			if ( in_array( $route, $allowed, true ) ) {
				$out[] = $route;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * 规范化自定义路由多行文本。
	 *
	 * @param string $text 原始文本。
	 * @return string
	 */
	public static function normalize_routes_text( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		$routes = array();
		foreach ( $lines as $line ) {
			$route = self::sanitize_route( $line );
			if ( '' !== $route ) {
				$routes[] = $route;
			}
		}
		return implode( "\n", array_values( array_unique( $routes ) ) );
	}

	/**
	 * 校验并规范化单条 REST 路由。
	 *
	 * @param string $route 原始路由。
	 * @return string 合法则返回规范化路由，否则空串。
	 */
	public static function sanitize_route( $route ) {
		$route = trim( (string) $route );
		if ( '' === $route ) {
			return '';
		}
		if ( 0 !== strpos( $route, '/' ) ) {
			$route = '/' . ltrim( $route, '/' );
		}
		// 仅允许 REST 路由常见字符，防止注入怪异键名。
		if ( ! preg_match( '/^\/[A-Za-z0-9_\-\/\(\)\?P\<\>\[\]\\\\d\+\|\.]+$/', $route ) ) {
			return '';
		}
		return $route;
	}

	/**
	 * 解析生效的 REST 黑名单（预置 + 自定义）。
	 *
	 * @return string[]
	 */
	public static function get_active_rest_routes() {
		$settings = self::get();
		$routes   = isset( $settings['rest_preset_routes'] ) && is_array( $settings['rest_preset_routes'] )
			? $settings['rest_preset_routes']
			: array();
		$text     = isset( $settings['rest_custom_routes'] ) ? (string) $settings['rest_custom_routes'] : '';
		$lines    = preg_split( '/\r\n|\r|\n/', $text );
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$route = self::sanitize_route( $line );
				if ( '' !== $route ) {
					$routes[] = $route;
				}
			}
		}
		return array_values( array_unique( $routes ) );
	}

	/**
	 * 不参与清理勾选的系统内部类型。
	 *
	 * @return string[]
	 */
	public static function excluded_system_post_types() {
		return array(
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
		);
	}

	/**
	 * 自动识别本站可清理的文章类型（含自定义 CPT）。
	 *
	 * 来源：已注册（public / show_ui / publicly_queryable）+ 数据库中实际存在的 post_type。
	 *
	 * @return array<string, array{label:string,group:string,count:int,slug:string}>
	 */
	public static function get_cleanup_candidate_post_types() {
		$excluded = self::excluded_system_post_types();
		$counts   = self::get_post_type_counts();
		$builtin  = array( 'post', 'page' );
		$result   = array();

		// 已注册且可管理/公开的类型。
		$registered = get_post_types( array(), 'objects' );
		foreach ( $registered as $slug => $obj ) {
			if ( in_array( $slug, $excluded, true ) ) {
				continue;
			}
			$include = ! empty( $obj->public ) || ! empty( $obj->show_ui ) || ! empty( $obj->publicly_queryable );
			if ( ! $include ) {
				continue;
			}
			$group = in_array( $slug, $builtin, true ) ? 'builtin' : 'custom';
			$result[ $slug ] = array(
				'slug'  => $slug,
				'label' => self::format_post_type_label( $slug, $obj ),
				'group' => $group,
				'count' => isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0,
			);
		}

		// 确保内置 post/page 始终出现。
		foreach ( $builtin as $slug ) {
			if ( isset( $result[ $slug ] ) ) {
				continue;
			}
			$obj = get_post_type_object( $slug );
			$result[ $slug ] = array(
				'slug'  => $slug,
				'label' => self::format_post_type_label( $slug, $obj ),
				'group' => 'builtin',
				'count' => isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0,
			);
		}

		// 数据库中有数据、但未纳入上方列表的类型（例如已停用插件留下的 CPT）。
		foreach ( $counts as $slug => $count ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || in_array( $slug, $excluded, true ) || isset( $result[ $slug ] ) ) {
				continue;
			}
			$obj = get_post_type_object( $slug );
			$result[ $slug ] = array(
				'slug'  => $slug,
				'label' => self::format_post_type_label( $slug, $obj ),
				'group' => $obj ? 'custom' : 'discovered',
				'count' => (int) $count,
			);
		}

		uasort(
			$result,
			static function ( $a, $b ) {
				$order = array(
					'builtin'    => 0,
					'custom'     => 1,
					'discovered' => 2,
				);
				$ga    = isset( $order[ $a['group'] ] ) ? $order[ $a['group'] ] : 9;
				$gb    = isset( $order[ $b['group'] ] ) ? $order[ $b['group'] ] : 9;
				if ( $ga !== $gb ) {
					return $ga - $gb;
				}
				return strcasecmp( $a['slug'], $b['slug'] );
			}
		);

		return $result;
	}

	/**
	 * 统计各 post_type 在 posts 表中的数量。
	 *
	 * @return array<string, int>
	 */
	public static function get_post_type_counts() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT post_type, COUNT(*) AS cnt FROM {$wpdb->posts} GROUP BY post_type", ARRAY_A );
		$out  = array();
		if ( empty( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$slug = isset( $row['post_type'] ) ? sanitize_key( (string) $row['post_type'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = isset( $row['cnt'] ) ? (int) $row['cnt'] : 0;
		}
		return $out;
	}

	/**
	 * 生成文章类型展示名。
	 *
	 * @param string        $slug 类型 slug。
	 * @param object|null   $obj  post type 对象。
	 * @return string
	 */
	public static function format_post_type_label( $slug, $obj = null ) {
		if ( $obj && ! empty( $obj->labels->singular_name ) ) {
			return (string) $obj->labels->singular_name;
		}
		if ( $obj && ! empty( $obj->label ) ) {
			return (string) $obj->label;
		}
		return (string) $slug;
	}

	/**
	 * 分组标题。
	 *
	 * @param string $group 分组键。
	 * @return string
	 */
	public static function cleanup_group_label( $group ) {
		$map = array(
			'builtin'    => __( '内置类型', 'wp-site-essentials' ),
			'custom'     => __( '已注册的自定义类型（CPT）', 'wp-site-essentials' ),
			'discovered' => __( '数据库中发现的其他类型', 'wp-site-essentials' ),
		);
		return isset( $map[ $group ] ) ? $map[ $group ] : $group;
	}

	/**
	 * 清洗可清理的文章类型（仅保留候选列表内的 slug）。
	 *
	 * @param mixed $types 原始值（数组或换行/逗号分隔字符串）。
	 * @return string[]
	 */
	public static function sanitize_post_types( $types ) {
		if ( is_string( $types ) ) {
			$types = preg_split( '/[\s,]+/', $types );
		}
		if ( ! is_array( $types ) ) {
			return array( 'post', 'page' );
		}
		$allowed = array_keys( self::get_cleanup_candidate_post_types() );
		$out     = array();
		foreach ( $types as $type ) {
			$type = sanitize_key( (string) $type );
			if ( '' !== $type && in_array( $type, $allowed, true ) ) {
				$out[] = $type;
			}
		}
		$out = array_values( array_unique( $out ) );
		return empty( $out ) ? array( 'post', 'page' ) : $out;
	}

	/**
	 * 获取清理用的文章类型列表（已保存的默认勾选）。
	 *
	 * @return string[]
	 */
	public static function get_cleanup_post_types() {
		$settings = self::get();
		return self::sanitize_post_types(
			isset( $settings['cleanup_post_types'] ) ? $settings['cleanup_post_types'] : array( 'post', 'page' )
		);
	}

	/**
	 * 是否在卸载时删除数据。
	 *
	 * @return bool
	 */
	public static function should_delete_data_on_uninstall() {
		$settings = self::get();
		return ! empty( $settings['delete_data_on_uninstall'] );
	}
}
