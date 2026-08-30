<?php
/**
 * Custom post types and their admin UI.
 *
 * @package FForms
 */

namespace FForms;

use WP_Post;

final class Post_Types {
	public const FORM  = 'fform';
	public const ENTRY = 'fform_entry';

	public static function boot(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::ENTRY, array( self::class, 'save_entry' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_form_settings_sidebar' ) );
		add_filter( 'block_editor_settings_all', array( self::class, 'configure_headless_editor' ), 10, 2 );
		add_filter( 'manage_' . self::ENTRY . '_posts_columns', array( self::class, 'entry_columns' ) );
		add_action( 'manage_' . self::ENTRY . '_posts_custom_column', array( self::class, 'render_entry_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( self::class, 'render_entries_form_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'filter_entries_by_form' ) );
		add_filter( 'post_row_actions', array( self::class, 'add_view_entries_row_action' ), 10, 2 );
		add_filter( 'the_title', array( self::class, 'append_entry_id_to_title' ), 10, 2 );
		add_filter( 'display_post_states', array( self::class, 'hide_entry_post_state' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( self::class, 'cache_compiled_schema' ), 10, 3 );
		add_filter( 'wp_insert_post_data', array( self::class, 'prevent_invalid_publish' ), 20, 2 );
		add_action( 'admin_notices', array( self::class, 'render_validation_notice' ) );
	}

	public static function register(): void {
		register_post_type(
			self::FORM,
			array(
				'labels' => array(
					'name'          => __( 'Формы', 'fforms' ),
					'singular_name' => __( 'Форма', 'fforms' ),
					'add_new_item'  => __( 'Добавить форму', 'fforms' ),
					'edit_item'     => __( 'Редактировать форму', 'fforms' ),
					'menu_name'     => __( 'FForms', 'fforms' ),
					'all_items'     => __( 'Формы', 'fforms' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'fforms',
				'show_in_rest'        => true,
				'rest_base'           => 'fforms',
				'menu_icon'           => 'dashicons-feedback',
				'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				'template'            => array(
					array( 'fforms/form', array(), array(
						array( 'fforms/field-text', array( 'fieldId' => 'name', 'name' => 'name', 'label' => __( 'Имя', 'fforms' ), 'required' => true ) ),
						array( 'fforms/field-email', array( 'fieldId' => 'email', 'name' => 'email', 'label' => __( 'Email', 'fforms' ), 'required' => true ) ),
						array( 'fforms/field-textarea', array( 'fieldId' => 'message', 'name' => 'message', 'label' => __( 'Сообщение', 'fforms' ), 'required' => true ) ),
						array( 'fforms/submit', array( 'label' => __( 'Отправить', 'fforms' ) ) ),
					) ),
				),
				'exclude_from_search' => true,
			)
		);

		register_post_type(
			self::ENTRY,
			array(
				'labels' => array(
					'name'          => __( 'Ответы', 'fforms' ),
					'singular_name' => __( 'Ответ', 'fforms' ),
					'edit_item'     => __( 'Просмотреть ответ', 'fforms' ),
					'menu_name'     => __( 'Ответы', 'fforms' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'fforms',
				'show_in_rest'        => false,
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
				'map_meta_cap'        => false,
				'capabilities'        => self::entry_capabilities(),
			)
		);

		self::register_meta();
	}

	private static function register_meta(): void {
		register_post_meta( self::FORM, '_fforms_type', array( 'type' => 'string', 'single' => true, 'default' => 'contact', 'show_in_rest' => true, 'sanitize_callback' => static fn( $value ): string => in_array( $value, array( 'contact', 'lead' ), true ) ? $value : 'contact' ) );
		register_post_meta( self::FORM, '_fforms_mode', array( 'type' => 'string', 'single' => true, 'default' => 'block', 'show_in_rest' => true, 'sanitize_callback' => array( self::class, 'sanitize_form_mode' ) ) );
		register_post_meta( self::FORM, '_fforms_schema', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( Schema::class, 'sanitize_json' ) ) );
		register_post_meta( self::FORM, '_fforms_schema_hash', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		register_post_meta( self::FORM, '_fforms_public', array( 'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => true ) );
		register_post_meta( self::FORM, '_fforms_notifications_enabled', array( 'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => true ) );

		foreach ( array( '_fforms_notification_to', '_fforms_notification_subject', '_fforms_success_message', '_fforms_autoreply_email_field', '_fforms_autoreply_subject', '_fforms_autoreply_message' ) as $key ) {
			register_post_meta( self::FORM, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => '_fforms_autoreply_message' === $key ? 'sanitize_textarea_field' : 'sanitize_text_field' ) );
		}
		register_post_meta( self::FORM, '_fforms_autoreply_enabled', array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true ) );
		foreach ( array( '_fforms_form_id', '_fforms_created_post_id' ) as $key ) {
			register_post_meta( self::ENTRY, $key, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false ) );
		}
		foreach ( array( '_fforms_form_key', '_fforms_data', '_fforms_status', '_fforms_source', '_fforms_ip', '_fforms_user_agent' ) as $key ) {
			register_post_meta( self::ENTRY, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		}
	}

	public static function sanitize_form_mode( mixed $value ): string {
		return 'headless' === $value ? 'headless' : 'block';
	}

	public static function form_mode( int $form_id ): string {
		if ( \FForms\Schema\Schema_Compiler::has_headless_schema_block( (string) get_post_field( 'post_content', $form_id ) ) ) {
			return 'headless';
		}

		return self::sanitize_form_mode( get_post_meta( $form_id, '_fforms_mode', true ) );
	}

	/** @param array<string,mixed> $settings */
	public static function configure_headless_editor( array $settings, \WP_Block_Editor_Context $context ): array {
		if ( ! isset( $context->post ) || ! $context->post instanceof WP_Post || self::FORM !== $context->post->post_type || 'headless' !== self::form_mode( $context->post->ID ) ) {
			return $settings;
		}

		$settings['template']     = self::headless_schema_template();
		$settings['templateLock'] = 'all';
		return $settings;
	}

	/** @return array<int, array<int|string, mixed>> */
	private static function headless_schema_template(): array {
		return array(
			array( 'fforms/headless-schema', array( 'lock' => array( 'move' => true, 'remove' => true ) ), array(
				array( 'fforms/field-text', array( 'fieldId' => 'name', 'name' => 'name', 'label' => __( 'Имя', 'fforms' ), 'required' => true ) ),
				array( 'fforms/field-email', array( 'fieldId' => 'email', 'name' => 'email', 'label' => __( 'Email', 'fforms' ), 'required' => true ) ),
				array( 'fforms/field-textarea', array( 'fieldId' => 'message', 'name' => 'message', 'label' => __( 'Сообщение', 'fforms' ), 'required' => true ) ),
			) ),
		);
	}

	public static function add_meta_boxes(): void {
		add_meta_box( 'fforms_entry_data', __( 'Данные ответа', 'fforms' ), array( self::class, 'render_entry_meta_box' ), self::ENTRY, 'normal', 'high' );
	}

	public static function enqueue_form_settings_sidebar(): void {
		$screen = get_current_screen();
		if ( ! $screen || self::FORM !== $screen->post_type ) {
			return;
		}

		$handle = 'fforms-form-settings-sidebar';
		$path   = FFORMS_DIR . 'assets/form-settings-sidebar.js';
		$version = file_exists( $path ) ? (string) filemtime( $path ) : FFORMS_VERSION;
		wp_enqueue_script(
			$handle,
			FFORMS_URL . 'assets/form-settings-sidebar.js',
			array( 'wp-blocks', 'wp-components', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			$version,
			true
		);
		wp_add_inline_script(
			$handle,
			'window.fformsFormSettings = ' . wp_json_encode(
				array(
					'publicFormUrl'          => Public_Form::url( 0 ),
					'notificationSettingsEnabled' => ! empty( Settings::get()['notifications'] ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Keep the old meta value only as a cache after normal editor, REST, or programmatic saves.
	 */
	public static function cache_compiled_schema( int $post_id, \WP_Post $post, bool $update ): void {
		if ( self::FORM !== $post->post_type || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( \FForms\Schema\Schema_Compiler::has_schema_block( $post->post_content ) ) {
			\FForms\Schema\Schema_Repository::invalidate( $post_id );
			$schema = \FForms\Schema\Schema_Repository::for_form( $post_id );
			if ( ! is_wp_error( $schema ) ) {
				\FForms\Schema\Schema_Repository::store_cache( $post_id, $schema );
			}
		}
	}

	/** @param array<string, mixed> $data @param array<string, mixed> $postarr */
	public static function prevent_invalid_publish( array $data, array $postarr ): array {
		// wp_insert_post_data provides slashed data; unslash before parsing block JSON attributes.
		$content = wp_unslash( (string) ( $data['post_content'] ?? '' ) );
		if ( self::FORM !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'private' ), true ) || ! \FForms\Schema\Schema_Compiler::has_schema_block( $content ) ) {
			return $data;
		}
		$schema = \FForms\Schema\Schema_Compiler::compile( $content );
		if ( is_wp_error( $schema ) ) {
			$data['post_status'] = 'draft';
			$error_data = $schema->get_error_data( 'fforms_invalid_block_schema' );
			$errors     = is_array( $error_data ) && isset( $error_data['errors'] ) ? (array) $error_data['errors'] : array( $schema->get_error_message() );
			set_transient( 'fforms_schema_error_' . get_current_user_id(), implode( ' ', $errors ), MINUTE_IN_SECONDS );
		}
		return $data;
	}

	public static function render_validation_notice(): void {
		$error = get_transient( 'fforms_schema_error_' . get_current_user_id() );
		if ( ! $error ) {
			return;
		}
		delete_transient( 'fforms_schema_error_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html( (string) $error ) . '</p></div>';
	}

	public static function render_entry_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'fforms_save_entry', 'fforms_entry_nonce' );
		$data   = json_decode( (string) get_post_meta( $post->ID, '_fforms_data', true ), true );
		$status = (string) get_post_meta( $post->ID, '_fforms_status', true );
		?>
		<p><label for="fforms_entry_status"><strong><?php esc_html_e( 'Статус', 'fforms' ); ?></strong></label><br><select id="fforms_entry_status" name="fforms_entry_status"><?php foreach ( array( 'new' => __( 'Новый', 'fforms' ), 'read' => __( 'Прочитан', 'fforms' ), 'replied' => __( 'Отвечен', 'fforms' ), 'spam' => __( 'Спам', 'fforms' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status ?: 'new', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
		<table class="widefat striped"><tbody><?php foreach ( is_array( $data ) ? $data : array() as $key => $value ) : ?><tr><th style="width:25%"><?php echo esc_html( (string) $key ); ?></th><td><?php echo nl2br( esc_html( self::stringify( $value ) ) ); ?></td></tr><?php endforeach; ?></tbody></table>
		<p><small><?php echo esc_html( sprintf( 'IP: %s · User-Agent: %s · Source: %s', get_post_meta( $post->ID, '_fforms_ip', true ), get_post_meta( $post->ID, '_fforms_user_agent', true ), get_post_meta( $post->ID, '_fforms_source', true ) ) ); ?></small></p>
		<?php
	}

	public static function save_entry( int $post_id ): void {
		if ( ! self::can_save( $post_id, 'fforms_entry_nonce', 'fforms_save_entry' ) ) {
			return;
		}
		$status = isset( $_POST['fforms_entry_status'] ) ? sanitize_key( wp_unslash( $_POST['fforms_entry_status'] ) ) : 'new';
		update_post_meta( $post_id, '_fforms_status', in_array( $status, array( 'new', 'read', 'replied', 'spam' ), true ) ? $status : 'new' );
	}

	public static function entry_columns( array $columns ): array {
		return array( 'cb' => $columns['cb'] ?? '<input type="checkbox" />', 'title' => __( 'Ответ', 'fforms' ), 'fforms_form' => __( 'Форма', 'fforms' ), 'fforms_status' => __( 'Статус', 'fforms' ), 'fforms_preview' => __( 'Данные', 'fforms' ), 'date' => $columns['date'] ?? __( 'Дата', 'fforms' ) );
	}

	public static function append_entry_id_to_title( string $title, int $post_id ): string {
		if ( is_admin() && self::ENTRY === get_post_type( $post_id ) ) {
			$title .= ' (#' . $post_id . ')';
		}
		return $title;
	}

	/** @param array<string, string> $states */
	public static function hide_entry_post_state( array $states, WP_Post $post ): array {
		if ( self::ENTRY === $post->post_type ) {
			unset( $states['private'] );
		}
		return $states;
	}

	public static function render_entry_column( string $column, int $post_id ): void {
		if ( 'fforms_form' === $column ) {
			$form_id  = (int) get_post_meta( $post_id, '_fforms_form_id', true );
			$form_key = (string) get_post_meta( $post_id, '_fforms_form_key', true );
			if ( $form_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $form_id ) ) . '">' . esc_html( get_the_title( $form_id ) ) . '</a>';
			} elseif ( '' !== $form_key ) {
				$ref = Form_Locator::resolve( $form_key );
				echo esc_html( is_wp_error( $ref ) ? $form_key : $ref->title );
			} else {
				echo '—';
			}
		} elseif ( 'fforms_status' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_fforms_status', true ) );
		} elseif ( 'fforms_preview' === $column ) {
			$data = json_decode( (string) get_post_meta( $post_id, '_fforms_data', true ), true );
			echo esc_html( self::stringify( is_array( $data ) ? array_slice( $data, 0, 3, true ) : array() ) );
		}
	}

	public static function render_entries_form_filter(): void {
		global $typenow;
		if ( self::ENTRY !== $typenow ) {
			return;
		}
		$current = isset( $_GET['form_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['form_ref'] ) ) : '';
		$forms   = get_posts( array( 'post_type' => self::FORM, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<label class="screen-reader-text" for="fforms-filter-form"><?php esc_html_e( 'Фильтр по форме', 'fforms' ); ?></label>
		<select id="fforms-filter-form" name="form_ref">
			<option value=""><?php esc_html_e( 'Все формы', 'fforms' ); ?></option>
			<?php foreach ( $forms as $form ) : ?>
				<option value="post:<?php echo esc_attr( $form->ID ); ?>" <?php selected( $current, 'post:' . $form->ID ); ?>><?php echo esc_html( get_the_title( $form ) ); ?></option>
			<?php endforeach; ?>
			<?php foreach ( Registry\Code_Forms::all() as $key => $code_form ) : ?>
				<option value="code:<?php echo esc_attr( $key ); ?>" <?php selected( $current, 'code:' . $key ); ?>><?php echo esc_html( $code_form->title ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function filter_entries_by_form( \WP_Query $query ): void {
		global $pagenow, $typenow;
		if ( ! is_admin() || 'edit.php' !== $pagenow || self::ENTRY !== $typenow || ! $query->is_main_query() ) {
			return;
		}
		$form_ref = isset( $_GET['form_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['form_ref'] ) ) : '';
		if ( str_starts_with( $form_ref, 'post:' ) ) {
			$query->set( 'meta_key', '_fforms_form_id' );
			$query->set( 'meta_value', absint( substr( $form_ref, 5 ) ) );
		} elseif ( str_starts_with( $form_ref, 'code:' ) ) {
			$query->set( 'meta_key', '_fforms_form_key' );
			$query->set( 'meta_value', sanitize_key( substr( $form_ref, 5 ) ) );
		}
	}

	/** @param array<string, string> $actions */
	public static function add_view_entries_row_action( array $actions, WP_Post $post ): array {
		if ( self::FORM === $post->post_type && current_user_can( 'manage_options' ) ) {
			$url = add_query_arg(
				array( 'post_type' => self::ENTRY, 'form_ref' => 'post:' . $post->ID ),
				admin_url( 'edit.php' )
			);
			$actions['fforms_view_entries'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Смотреть заявки', 'fforms' ) . '</a>';
		}
		return $actions;
	}

	public static function stringify( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Да', 'fforms' ) : __( 'Нет', 'fforms' );
		}
		return is_array( $value ) ? implode( ', ', array_map( array( self::class, 'stringify' ), $value ) ) : (string) $value;
	}

	private static function can_save( int $post_id, string $nonce_key, string $action ): bool {
		return ! wp_is_post_autosave( $post_id ) && ! wp_is_post_revision( $post_id ) && isset( $_POST[ $nonce_key ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), $action ) && current_user_can( 'edit_post', $post_id );
	}

	private static function entry_capabilities(): array {
		return array(
			'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options',
			'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
			'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options',
			'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options',
			'edit_published_posts' => 'manage_options', 'create_posts' => 'do_not_allow',
		);
	}
}
