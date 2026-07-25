<?php
/**
 * 插件卸载清理。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * 清理当前站点数据（仅在设置允许时）。
 *
 * @return void
 */
function wpse_uninstall_site_data() {
	$settings = get_option( 'wpse_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}
	delete_option( 'wpse_settings' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		wpse_uninstall_site_data();
		restore_current_blog();
	}
} else {
	wpse_uninstall_site_data();
}
