<?php
/**
 * 「站点基础」设置页。
 *
 * @package WPSiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册设置菜单并渲染多区块表单与清理工具。
 */
class WPSE_Settings_Page {

	const PAGE_SLUG = 'wpse-site-essentials';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * 添加「设置 → 站点基础」。
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( '站点基础', 'wp-site-essentials' ),
			__( '站点基础', 'wp-site-essentials' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * 加载本页脚本样式。
	 *
	 * @param string $hook 当前后台页面 hook。
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wpse-admin',
			WPSE_URL . 'assets/css/admin.css',
			array(),
			WPSE_VERSION
		);
		wp_enqueue_script(
			'wpse-admin',
			WPSE_URL . 'assets/js/admin.js',
			array(),
			WPSE_VERSION,
			true
		);
		wp_enqueue_script(
			'wpse-cleanup',
			WPSE_URL . 'assets/js/cleanup.js',
			array(),
			WPSE_VERSION,
			true
		);
		wp_localize_script(
			'wpse-cleanup',
			'WPSE_CLEANUP',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpse_cleanup_run' ),
				'i18n'    => array(
					'selectOne'  => __( '请选择清理项', 'wp-site-essentials' ),
					'selectType' => __( '请至少选择一种文章类型（历史版本 / 自动草稿 / 回收站需要）', 'wp-site-essentials' ),
					'done'       => __( '全部清理完成', 'wp-site-essentials' ),
					'fail'       => __( '失败', 'wp-site-essentials' ),
					'start'      => __( '开始…', 'wp-site-essentials' ),
					'typeOps'    => array( 'revisions', 'auto_drafts', 'trash_posts' ),
					'labels'        => array(
						'revisions'          => __( '清理历史版本', 'wp-site-essentials' ),
						'auto_drafts'        => __( '清理自动草稿', 'wp-site-essentials' ),
						'trash_posts'        => __( '清理回收站文章', 'wp-site-essentials' ),
						'spam_comments'      => __( '清理垃圾评论', 'wp-site-essentials' ),
						'expired_transients' => __( '清理过期 transients', 'wp-site-essentials' ),
						'orphan_meta'        => __( '清理孤立 meta', 'wp-site-essentials' ),
					),
				),
			)
		);
	}

	/**
	 * 渲染按分组排列的文章类型勾选列表。
	 *
	 * @param array<string, array{label:string,group:string,count:int,slug:string}> $candidates 候选类型。
	 * @param string[]                                                               $checked    已勾选 slug。
	 * @param string                                                                 $input_name 表单 name。
	 * @return void
	 */
	private function render_post_type_checkboxes( $candidates, $checked, $input_name ) {
		$grouped = array(
			'builtin'    => array(),
			'custom'     => array(),
			'discovered' => array(),
		);
		foreach ( $candidates as $slug => $item ) {
			$group = isset( $item['group'] ) ? $item['group'] : 'custom';
			if ( ! isset( $grouped[ $group ] ) ) {
				$grouped[ $group ] = array();
			}
			$grouped[ $group ][ $slug ] = $item;
		}

		foreach ( $grouped as $group => $items ) {
			if ( empty( $items ) ) {
				continue;
			}
			echo '<div class="wpse-pt-group">';
			echo '<p class="wpse-pt-group__title"><strong>' . esc_html( WPSE_Settings::cleanup_group_label( $group ) ) . '</strong></p>';
			if ( 'discovered' === $group ) {
				echo '<p class="description">' . esc_html__( '这些类型在数据库中仍有内容，但当前未注册（可能来自已停用的主题/插件）。', 'wp-site-essentials' ) . '</p>';
			}
			echo '<fieldset class="wpse-pt-group__list">';
			foreach ( $items as $slug => $item ) {
				$label = isset( $item['label'] ) ? $item['label'] : $slug;
				$count = isset( $item['count'] ) ? (int) $item['count'] : 0;
				printf(
					'<label class="wpse-pt-opt"><input type="checkbox" name="%1$s" value="%2$s" data-wpse-group="%6$s" %3$s /> <span class="wpse-pt-opt__label">%4$s</span> <code>%2$s</code> <span class="wpse-pt-opt__count">(%5$s)</span></label>',
					esc_attr( $input_name ),
					esc_attr( $slug ),
					checked( in_array( $slug, $checked, true ), true, false ),
					esc_html( $label ),
					esc_html(
						sprintf(
							/* translators: %d: post count */
							__( '%d 条', 'wp-site-essentials' ),
							$count
						)
					),
					esc_attr( $group )
				);
			}
			echo '</fieldset></div>';
		}
	}

	/**
	 * 保存设置表单。
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! isset( $_POST['wpse_settings_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'wpse_save_settings', 'wpse_settings_nonce' );

		$input = isset( $_POST['wpse'] ) && is_array( $_POST['wpse'] )
			? wp_unslash( $_POST['wpse'] )
			: array();

		$raw_custom = isset( $input['rest_custom_routes'] ) ? (string) $input['rest_custom_routes'] : '';
		$raw_lines  = preg_split( '/\r\n|\r|\n/', $raw_custom );
		$invalid    = array();
		if ( is_array( $raw_lines ) ) {
			foreach ( $raw_lines as $line ) {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}
				if ( '' === WPSE_Settings::sanitize_route( $line ) ) {
					$invalid[] = $line;
				}
			}
		}

		WPSE_Settings::update( $input );

		add_settings_error(
			'wpse_settings',
			'wpse_saved',
			__( '设置已保存。', 'wp-site-essentials' ),
			'success'
		);

		if ( ! empty( $invalid ) ) {
			add_settings_error(
				'wpse_settings',
				'wpse_invalid_routes',
				sprintf(
					/* translators: %s: invalid routes */
					__( '以下自定义 REST 路由格式无效已忽略：%s', 'wp-site-essentials' ),
					implode( ', ', array_map( 'esc_html', $invalid ) )
				),
				'warning'
			);
		}
	}

	/**
	 * 渲染设置页。
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings      = WPSE_Settings::get();
		$presets       = WPSE_Settings::rest_preset_routes();
		$selected      = isset( $settings['rest_preset_routes'] ) ? $settings['rest_preset_routes'] : array();
		$cleanup_types = WPSE_Settings::get_cleanup_post_types();
		$candidates    = WPSE_Settings::get_cleanup_candidate_post_types();
		?>
		<div class="wrap wpse-wrap">
			<h1><?php esc_html_e( '站点基础', 'wp-site-essentials' ); ?></h1>
			<?php settings_errors( 'wpse_settings' ); ?>

			<nav class="nav-tab-wrapper wpse-tabs" aria-label="<?php esc_attr_e( '设置分组', 'wp-site-essentials' ); ?>">
				<a href="#wpse-tab-url" class="nav-tab nav-tab-active" data-wpse-tab="wpse-tab-url"><?php esc_html_e( 'URL 规范', 'wp-site-essentials' ); ?></a>
				<a href="#wpse-tab-rest" class="nav-tab" data-wpse-tab="wpse-tab-rest"><?php esc_html_e( '敏感 REST', 'wp-site-essentials' ); ?></a>
				<a href="#wpse-tab-comments" class="nav-tab" data-wpse-tab="wpse-tab-comments"><?php esc_html_e( '全局禁评', 'wp-site-essentials' ); ?></a>
				<a href="#wpse-tab-cleanup" class="nav-tab" data-wpse-tab="wpse-tab-cleanup"><?php esc_html_e( '站点清理', 'wp-site-essentials' ); ?></a>
				<a href="#wpse-tab-uninstall" class="nav-tab" data-wpse-tab="wpse-tab-uninstall"><?php esc_html_e( '卸载', 'wp-site-essentials' ); ?></a>
			</nav>

			<form method="post" action="" class="wpse-settings-form">
				<?php wp_nonce_field( 'wpse_save_settings', 'wpse_settings_nonce' ); ?>

				<section id="wpse-tab-url" class="wpse-panel is-active">
					<h2><?php esc_html_e( 'URL 规范', 'wp-site-essentials' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( '大写转小写', 'wp-site-essentials' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="wpse[enable_url_lowercase]" value="1" <?php checked( ! empty( $settings['enable_url_lowercase'] ) ); ?> />
									<?php esc_html_e( '启用：将前台 URL 路径中的大写字母 301 重定向为小写', 'wp-site-essentials' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( '仅处理 GET/HEAD；保留 query；跳过 wp-admin / wp-content / wp-includes 与带扩展名的静态资源。', 'wp-site-essentials' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</section>

				<section id="wpse-tab-rest" class="wpse-panel">
					<h2><?php esc_html_e( '敏感 REST', 'wp-site-essentials' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( '启用模块', 'wp-site-essentials' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="wpse[enable_rest_hardening]" value="1" <?php checked( ! empty( $settings['enable_rest_hardening'] ) ); ?> />
									<?php esc_html_e( '对未登录用户隐藏下方勾选的 REST 路由', 'wp-site-essentials' ); ?>
								</label>
								<p class="description"><?php esc_html_e( '已登录用户不受影响。模块关闭或未勾选任何路由时不生效。', 'wp-site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( '预置路由', 'wp-site-essentials' ); ?></th>
							<td>
								<fieldset>
									<?php foreach ( $presets as $route => $label ) : ?>
										<label style="display:block;margin:0.35rem 0;">
											<input type="checkbox" name="wpse[rest_preset_routes][]" value="<?php echo esc_attr( $route ); ?>" <?php checked( in_array( $route, $selected, true ) ); ?> />
											<code><?php echo esc_html( $route ); ?></code>
											— <?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpse-rest-custom"><?php esc_html_e( '自定义路由', 'wp-site-essentials' ); ?></label></th>
							<td>
								<textarea id="wpse-rest-custom" name="wpse[rest_custom_routes]" rows="6" cols="60" class="large-text code"><?php echo esc_textarea( (string) $settings['rest_custom_routes'] ); ?></textarea>
								<p class="description"><?php esc_html_e( '每行一条，须以 / 开头，例如 /wp/v2/users。非法行保存时会被忽略。', 'wp-site-essentials' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section id="wpse-tab-comments" class="wpse-panel">
					<h2><?php esc_html_e( '全局禁评', 'wp-site-essentials' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( '禁用评论', 'wp-site-essentials' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="wpse[enable_disable_comments]" value="1" <?php checked( ! empty( $settings['enable_disable_comments'] ) ); ?> />
									<?php esc_html_e( '全局关闭评论与 trackback（含后台入口、REST 评论、pingback）', 'wp-site-essentials' ); ?>
								</label>
								<p class="description"><?php esc_html_e( '开启后将拦截 wp-comments-post.php，并隐藏评论相关菜单与管理栏图标。', 'wp-site-essentials' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section id="wpse-tab-cleanup" class="wpse-panel">
					<!-- 清理 UI 在表单外的 #wpse-cleanup-tool，避免与设置表单字段重复。 -->
				</section>

				<section id="wpse-tab-uninstall" class="wpse-panel">
					<h2><?php esc_html_e( '卸载', 'wp-site-essentials' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( '删除数据', 'wp-site-essentials' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="wpse[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
									<?php esc_html_e( '卸载插件时删除本插件的全部设置数据', 'wp-site-essentials' ); ?>
								</label>
								<p class="description"><?php esc_html_e( '默认不勾选：卸载后配置保留，便于重装恢复。', 'wp-site-essentials' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<p class="submit wpse-global-submit">
					<?php submit_button( __( '保存设置', 'wp-site-essentials' ), 'primary', 'submit', false ); ?>
				</p>
			</form>

			<div id="wpse-cleanup-tool" class="wpse-cleanup-box" data-wpse-show-when="wpse-tab-cleanup" hidden>
				<h2><?php esc_html_e( '站点清理', 'wp-site-essentials' ); ?></h2>
				<p class="description">
					<?php esc_html_e( '自动识别内置类型、已注册 CPT，以及数据库中仍有内容的类型。勾选后执行清理；不会删除已发布/正常使用的内容，只清理对应类型的历史版本、自动草稿与回收站（见下方清理项目）。', 'wp-site-essentials' ); ?>
				</p>

				<div id="wpse-cleanup-form">
					<div class="wpse-cleanup-section">
						<div class="wpse-cleanup-section__head">
							<strong><?php esc_html_e( '1. 文章类型范围', 'wp-site-essentials' ); ?></strong>
							<span class="wpse-cleanup-quick">
								<button type="button" class="button-link" id="wpse-pt-select-all"><?php esc_html_e( '全选', 'wp-site-essentials' ); ?></button>
								|
								<button type="button" class="button-link" id="wpse-pt-select-builtin"><?php esc_html_e( '仅内置', 'wp-site-essentials' ); ?></button>
							</span>
						</div>
						<p class="description"><?php esc_html_e( '「全选 / 仅内置」只改当前页面勾选，不写数据库。「开始清理」也按当前页面勾选执行，不会去读已保存的默认值。勾选作用于：历史版本、自动草稿、回收站；垃圾评论 / transient / 孤立 meta 与文章类型无关。', 'wp-site-essentials' ); ?></p>
						<?php $this->render_post_type_checkboxes( $candidates, $cleanup_types, 'post_types[]' ); ?>
					</div>

					<div class="wpse-cleanup-section">
						<p class="wpse-cleanup-section__head"><strong><?php esc_html_e( '2. 清理项目', 'wp-site-essentials' ); ?></strong></p>
						<label class="wpse-cleanup-opt"><input type="checkbox" name="ops[]" value="revisions" checked /> <?php esc_html_e( '清理历史版本（每篇保留最新 3 个）', 'wp-site-essentials' ); ?></label>
						<label class="wpse-cleanup-opt"><input type="checkbox" name="ops[]" value="auto_drafts" checked /> <?php esc_html_e( '清理自动草稿', 'wp-site-essentials' ); ?></label>
						<label class="wpse-cleanup-opt"><input type="checkbox" name="ops[]" value="trash_posts" checked /> <?php esc_html_e( '清理回收站文章', 'wp-site-essentials' ); ?></label>
						<label class="wpse-cleanup-opt"><input type="checkbox" name="ops[]" value="spam_comments" checked /> <?php esc_html_e( '清理垃圾评论', 'wp-site-essentials' ); ?></label>
						<label class="wpse-cleanup-opt"><input type="checkbox" name="ops[]" value="expired_transients" checked /> <?php esc_html_e( '清理过期 transients', 'wp-site-essentials' ); ?></label>
						<label class="wpse-cleanup-opt"><input type="checkbox" name="ops[]" value="orphan_meta" checked /> <?php esc_html_e( '清理孤立 post meta 和 comment meta', 'wp-site-essentials' ); ?></label>
					</div>

					<p class="wpse-cleanup-actions">
						<button type="button" class="button button-primary" id="wpse-cleanup-run"><?php esc_html_e( '开始清理', 'wp-site-essentials' ); ?></button>
					</p>
				</div>
				<div id="wpse-cleanup-progress" class="wpse-cleanup-progress" hidden>
					<div id="wpse-cleanup-bar" class="wpse-cleanup-bar"></div>
				</div>
				<div id="wpse-cleanup-log" class="wpse-cleanup-log" aria-live="polite"></div>
			</div>
		</div>
		<?php
	}
}
