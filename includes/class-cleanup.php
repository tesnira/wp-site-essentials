<?php
/**
 * 站点清理工具。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX 清理修订、草稿、回收站、垃圾评论、transient、孤立 meta。
 */
class WPSE_Cleanup {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_wpse_cleanup_revisions', array( $this, 'ajax_revisions' ) );
		add_action( 'wp_ajax_wpse_cleanup_auto_drafts', array( $this, 'ajax_auto_drafts' ) );
		add_action( 'wp_ajax_wpse_cleanup_trash_posts', array( $this, 'ajax_trash_posts' ) );
		add_action( 'wp_ajax_wpse_cleanup_spam_comments', array( $this, 'ajax_spam_comments' ) );
		add_action( 'wp_ajax_wpse_cleanup_expired_transients', array( $this, 'ajax_expired_transients' ) );
		add_action( 'wp_ajax_wpse_cleanup_orphan_meta', array( $this, 'ajax_orphan_meta' ) );
	}

	/**
	 * 校验权限与 nonce。
	 *
	 * @return void
	 */
	private function require_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpse_cleanup_run' ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 403 );
		}
	}

	/**
	 * 从请求解析本次清理的文章类型。
	 * 正常由前端提交页面勾选；若请求未带类型则回退到设置里的默认值。
	 *
	 * @param bool $required 是否必须至少选一种类型。
	 * @return string[]
	 */
	private function resolve_post_types( $required = true ) {
		$raw = array();
		if ( isset( $_POST['post_types'] ) ) {
			$raw = wp_unslash( $_POST['post_types'] );
		}
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,]+/', $raw );
		}
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			$types = WPSE_Settings::get_cleanup_post_types();
		} else {
			$types = WPSE_Settings::sanitize_post_types( $raw );
		}
		if ( $required && empty( $types ) ) {
			wp_send_json_error(
				array(
					'message' => __( '请至少选择一种文章类型。', 'wp-site-essentials' ),
				),
				400
			);
		}
		return $types;
	}

	/**
	 * 调试日志。
	 *
	 * @param string $message 消息。
	 * @return void
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[wpse_cleanup] ' . $message );
		}
	}

	/**
	 * 清理历史修订（每篇保留最新 3 个）。
	 *
	 * @return void
	 */
	public function ajax_revisions() {
		$this->require_ajax();
		$this->log( 'ajax_revisions_start' );
		global $wpdb;

		$types   = $this->resolve_post_types( true );
		$in_list = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
		$parents = $wpdb->get_col(
			"SELECT DISTINCT r.post_parent FROM {$wpdb->posts} r
			INNER JOIN {$wpdb->posts} p ON p.ID = r.post_parent
			WHERE r.post_type='revision' AND r.post_parent>0 AND p.post_type IN ({$in_list})"
		);

		$deleted = 0;
		if ( ! empty( $parents ) ) {
			foreach ( $parents as $pid ) {
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=%d ORDER BY post_date DESC",
						(int) $pid
					)
				);
				if ( ! empty( $ids ) && count( $ids ) > 3 ) {
					$remove = array_slice( $ids, 3 );
					foreach ( $remove as $rid ) {
						$res = function_exists( 'wp_delete_post_revision' )
							? wp_delete_post_revision( (int) $rid )
							: wp_delete_post( (int) $rid, true );
						if ( $res ) {
							++$deleted;
						}
					}
				}
			}
		}

		$this->log( 'ajax_revisions_deleted:' . $deleted );
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '删除 %d 个修订', 'wp-site-essentials' ),
					$deleted
				),
			)
		);
	}

	/**
	 * 清理自动草稿。
	 *
	 * @return void
	 */
	public function ajax_auto_drafts() {
		$this->require_ajax();
		$types   = $this->resolve_post_types( true );
		$deleted = 0;
		foreach ( $types as $type ) {
			$query = new WP_Query(
				array(
					'post_type'              => $type,
					'post_status'            => 'auto-draft',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( ! empty( $query->posts ) ) {
				foreach ( $query->posts as $pid ) {
					wp_delete_post( (int) $pid, true );
					++$deleted;
				}
			}
		}
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '删除 %d 个自动草稿', 'wp-site-essentials' ),
					$deleted
				),
			)
		);
	}

	/**
	 * 清理回收站文章。
	 *
	 * @return void
	 */
	public function ajax_trash_posts() {
		$this->require_ajax();
		$types   = $this->resolve_post_types( true );
		$deleted = 0;
		foreach ( $types as $type ) {
			$query = new WP_Query(
				array(
					'post_type'              => $type,
					'post_status'            => 'trash',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( ! empty( $query->posts ) ) {
				foreach ( $query->posts as $pid ) {
					wp_delete_post( (int) $pid, true );
					++$deleted;
				}
			}
		}
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '删除 %d 个回收站条目', 'wp-site-essentials' ),
					$deleted
				),
			)
		);
	}

	/**
	 * 清理垃圾与回收站评论。
	 *
	 * @return void
	 */
	public function ajax_spam_comments() {
		$this->require_ajax();
		global $wpdb;
		$ids     = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" );
		$deleted = 0;
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $cid ) {
				wp_delete_comment( (int) $cid, true );
				++$deleted;
			}
		}
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '删除 %d 个垃圾评论', 'wp-site-essentials' ),
					$deleted
				),
			)
		);
	}

	/**
	 * 清理过期 transient。
	 *
	 * @return void
	 */
	public function ajax_expired_transients() {
		$this->require_ajax();
		global $wpdb;
		$now     = time();
		$rows    = $wpdb->get_results(
			"SELECT option_id, option_name, option_value FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_timeout_%' OR option_name LIKE '_site_transient_timeout_%'"
		);
		$deleted = 0;
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$timeout = (int) $row->option_value;
				if ( $timeout > 0 && $timeout < $now ) {
					if ( 0 === strpos( $row->option_name, '_site_transient_timeout_' ) ) {
						$key = substr( $row->option_name, strlen( '_site_transient_timeout_' ) );
						delete_option( '_site_transient_' . $key );
					} else {
						$key = substr( $row->option_name, strlen( '_transient_timeout_' ) );
						delete_option( '_transient_' . $key );
					}
					delete_option( $row->option_name );
					++$deleted;
				}
			}
		}
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '删除 %d 个过期项', 'wp-site-essentials' ),
					$deleted
				),
			)
		);
	}

	/**
	 * 按 meta_id 删除 meta。
	 *
	 * @param string $type post|comment。
	 * @param int    $mid  meta ID。
	 * @return bool
	 */
	private function delete_meta_by_mid( $type, $mid ) {
		if ( function_exists( 'delete_metadata_by_mid' ) ) {
			return (bool) delete_metadata_by_mid( $type, $mid );
		}
		global $wpdb;
		if ( 'post' === $type ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_id=%d", (int) $mid ) );
			return true;
		}
		if ( 'comment' === $type ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->commentmeta} WHERE meta_id=%d", (int) $mid ) );
			return true;
		}
		return false;
	}

	/**
	 * 清理孤立 post/comment meta。
	 *
	 * @return void
	 */
	public function ajax_orphan_meta() {
		$this->require_ajax();
		global $wpdb;
		$pids    = $wpdb->get_col(
			"SELECT pm.meta_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL"
		);
		$cids    = $wpdb->get_col(
			"SELECT cm.meta_id FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL"
		);
		$deleted = 0;
		if ( ! empty( $pids ) ) {
			foreach ( $pids as $mid ) {
				$this->delete_meta_by_mid( 'post', (int) $mid );
				++$deleted;
			}
		}
		if ( ! empty( $cids ) ) {
			foreach ( $cids as $mid ) {
				$this->delete_meta_by_mid( 'comment', (int) $mid );
				++$deleted;
			}
		}
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '删除 %d 个孤立 meta', 'wp-site-essentials' ),
					$deleted
				),
			)
		);
	}
}
