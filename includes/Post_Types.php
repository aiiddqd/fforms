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
		add_filter( 'allowed_block_types_all', array( self::class, 'limit_internal_blocks_to_form_editor' ), 10, 2 );
		add_filter( 'manage_' . self::ENTRY . '_posts_columns', array( self::class, 'entry_columns' ) );
		add_action( 'manage_' . self::ENTRY . '_posts_custom_column', array( self::class, 'render_entry_column' ), 10, 2 );
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
					'name'          => __( 'Forms', 'fforms' ),
					'singular_name' => __( 'Form', 'fforms' ),
					'add_new_item'  => __( 'Add form', 'fforms' ),
					'edit_item'     => __( 'Edit form', 'fforms' ),
					'menu_name'     => __( 'FForms', 'fforms' ),
					'all_items'     => __( 'Forms', 'fforms' ),
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
						array( 'fforms/field-text', array( 'fieldId' => 'name', 'name' => 'name', 'label' => __( 'Name', 'fforms' ), 'required' => true ) ),
						array( 'fforms/field-email', array( 'fieldId' => 'email', 'name' => 'email', 'label' => __( 'Email', 'fforms' ), 'required' => true ) ),
						array( 'fforms/field-textarea', array( 'fieldId' => 'message', 'name' => 'message', 'label' => __( 'Message', 'fforms' ), 'required' => true ) ),
						array( 'fforms/submit', array( 'label' => __( 'Send', 'fforms' ) ) ),
					) ),
				),
				'exclude_from_search' => true,
			)
		);

		register_post_type(
			self::ENTRY,
			array(
				'labels' => array(
					'name'               => __( 'Submissions', 'fforms' ),
					'singular_name'      => __( 'Submission', 'fforms' ),
					'menu_name'          => __( 'Submissions', 'fforms' ),
					'all_items'          => __( 'Submissions', 'fforms' ),
					'edit_item'          => __( 'View submission', 'fforms' ),
					'view_item'          => __( 'View submission', 'fforms' ),
					'search_items'       => __( 'Search submissions', 'fforms' ),
					'not_found'          => __( 'No submissions found.', 'fforms' ),
					'not_found_in_trash' => __( 'No submissions found in Trash.', 'fforms' ),
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
		self::register_form_meta( '_fforms_share_link', array( 'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => true ) );
		self::register_form_meta( '_fforms_share_layout', array( 'type' => 'string', 'single' => true, 'default' => Public_Form::LAYOUT_SITE, 'show_in_rest' => true, 'sanitize_callback' => array( Public_Form::class, 'sanitize_layout' ) ) );
		self::register_form_meta( '_fforms_share_token', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( Public_Form::class, 'sanitize_token' ) ) );
		self::register_form_meta( '_fforms_schema', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( Schema::class, 'sanitize_json' ) ) );
		self::register_form_meta( '_fforms_schema_hash', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		self::register_form_meta( '_fforms_notifications_enabled', array( 'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => true ) );

		foreach ( array( '_fforms_notification_to', '_fforms_notification_subject', '_fforms_success_message', '_fforms_autoreply_email_field', '_fforms_autoreply_subject', '_fforms_autoreply_message' ) as $key ) {
			self::register_form_meta( $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => '_fforms_autoreply_message' === $key ? 'sanitize_textarea_field' : 'sanitize_text_field' ) );
		}
		self::register_form_meta( '_fforms_autoreply_enabled', array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true ) );
		foreach ( array( '_fforms_form_id', '_fforms_created_post_id', '_fforms_user_id' ) as $key ) {
			register_post_meta( self::ENTRY, $key, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false ) );
		}
		foreach ( array( '_fforms_form_key', '_fforms_data', '_fforms_status', '_fforms_source', '_fforms_ip', '_fforms_user_agent', '_fforms_custom', '_fforms_meta', '_fforms_ref', '_fforms_form_type_raw' ) as $key ) {
			register_post_meta( self::ENTRY, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		}
	}

	/** @param array<string, mixed> $args */
	private static function register_form_meta( string $key, array $args ): void {
		$args['auth_callback'] = array( self::class, 'can_edit_form_meta' );
		register_post_meta( self::FORM, $key, $args );
	}

	/**
	 * Protected form meta is exposed to Gutenberg REST only for users who can edit the form.
	 */
	public static function can_edit_form_meta( mixed $allowed, string $meta_key, int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Field-definition blocks build a form schema and have no meaning in posts
	 * or pages. Keep them available exclusively while editing an FForms CPT.
	 *
	 * @param bool|array<int, string> $allowed_block_types Allowed block names.
	 * @return bool|array<int, string>
	 */
	public static function limit_internal_blocks_to_form_editor( bool|array $allowed_block_types, \WP_Block_Editor_Context $context ): bool|array {
		if ( isset( $context->post ) && $context->post instanceof WP_Post && self::FORM === $context->post->post_type ) {
			return $allowed_block_types;
		}
		if ( ! is_array( $allowed_block_types ) && true !== $allowed_block_types ) {
			return $allowed_block_types;
		}

		$internal_blocks = array_merge(
			array( 'fforms/submit' ),
			array_map( static fn( string $type ): string => 'fforms/field-' . $type, array( 'text', 'textarea', 'email', 'tel', 'url', 'number', 'select', 'radio', 'checkbox', 'hidden' ) )
		);
		if ( true === $allowed_block_types ) {
			$allowed_block_types = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
		}

		return array_values( array_diff( $allowed_block_types, $internal_blocks ) );
	}

	public static function add_meta_boxes(): void {
		add_meta_box( 'fforms_entry_data', __( 'Submission data', 'fforms' ), array( self::class, 'render_entry_meta_box' ), self::ENTRY, 'normal', 'high' );
		add_meta_box( 'fforms_entry_extras', __( 'Additional data', 'fforms' ), array( self::class, 'render_entry_extras_meta_box' ), self::ENTRY, 'normal', 'default' );
	}

	public static function enqueue_form_settings_sidebar(): void {
		$screen = get_current_screen();
		if ( ! $screen || self::FORM !== $screen->post_type ) {
			return;
		}

		$post_id = (int) ( get_post()->ID ?? 0 );
		$handle = 'fforms-form-settings-sidebar';
		$path   = FFORMS_DIR . 'assets/form-settings-sidebar.js';
		$version = file_exists( $path ) ? (string) filemtime( $path ) : FFORMS_VERSION;
		wp_enqueue_script(
			$handle,
			FFORMS_URL . 'assets/form-settings-sidebar.js',
			array( 'wp-api-fetch', 'wp-components', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			$version,
			true
		);
		wp_set_script_translations( $handle, 'fforms', FFORMS_DIR . 'languages' );
		wp_add_inline_script(
			$handle,
			'window.fformsFormSettings = ' . wp_json_encode(
				array(
					// The token itself comes from post meta, so the panel updates as
					// soon as publishing issues one — no editor reload required.
					'shareUrlTemplate'            => home_url( user_trailingslashit( 'forms/{token}' ) ),
					'embedScriptUrl'              => Public_Form::embed_script_url(),
					'homeUrl'                     => home_url(),
					'notificationSettingsEnabled' => ! empty( Settings::get()['notifications'] ),
					'entriesUrl'                  => self::entries_url_for_form( $post_id ),
					'entriesCount'                => self::entries_count_for_form( $post_id ),
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
		if ( \FForms\Schema\Schema_Compiler::has_form_block( $post->post_content ) ) {
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
		if ( self::FORM !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'private' ), true ) || ! \FForms\Schema\Schema_Compiler::has_form_block( $content ) ) {
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
		<p><label for="fforms_entry_status"><strong><?php esc_html_e( 'Status', 'fforms' ); ?></strong></label><br><select id="fforms_entry_status" name="fforms_entry_status"><?php foreach ( array( 'new' => __( 'New', 'fforms' ), 'read' => __( 'Read', 'fforms' ), 'replied' => __( 'Replied', 'fforms' ), 'spam' => __( 'Spam', 'fforms' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status ?: 'new', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
		<table class="widefat striped"><tbody><?php foreach ( is_array( $data ) ? $data : array() as $key => $value ) : ?><tr><th style="width:25%"><?php echo esc_html( (string) $key ); ?></th><td><?php echo nl2br( esc_html( self::stringify( $value ) ) ); ?></td></tr><?php endforeach; ?></tbody></table>
		<p><small><?php echo esc_html( sprintf( 'IP: %s · User-Agent: %s · Source: %s', get_post_meta( $post->ID, '_fforms_ip', true ), get_post_meta( $post->ID, '_fforms_user_agent', true ), get_post_meta( $post->ID, '_fforms_source', true ) ) ); ?></small></p>
		<?php
	}

	public static function render_entry_extras_meta_box( WP_Post $post ): void {
		$type    = Form_Types::entry_type_slug( $post->ID );
		$term    = '' === $type ? null : Form_Types::get_term( $type );
		$ref     = (string) get_post_meta( $post->ID, '_fforms_ref', true );
		$user_id = (int) get_post_meta( $post->ID, '_fforms_user_id', true );
		$custom  = json_decode( (string) get_post_meta( $post->ID, '_fforms_custom', true ), true );
		$meta    = json_decode( (string) get_post_meta( $post->ID, '_fforms_meta', true ), true );

		if ( '' === $type && '' === $ref && ! $user_id && ! is_array( $custom ) && ! is_array( $meta ) ) {
			echo '<p>' . esc_html__( 'No additional data.', 'fforms' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped"><tbody>
			<?php if ( '' !== $type ) : ?>
				<tr><th style="width:25%"><?php esc_html_e( 'Form type', 'fforms' ); ?></th><td>
					<?php if ( $term ) : ?>
						<a href="<?php echo esc_url( (string) get_edit_term_link( $term->term_id, Form_Types::TAXONOMY ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
						<code><?php echo esc_html( $term->slug ); ?></code>
					<?php else : ?>
						<code><?php echo esc_html( $type ); ?></code>
						<span class="description"><?php esc_html_e( '— the term was not created (form type limit reached)', 'fforms' ); ?></span>
					<?php endif; ?>
				</td></tr>
			<?php endif; ?>
			<?php if ( '' !== $ref ) : ?>
				<tr><th><?php esc_html_e( 'Ref', 'fforms' ); ?></th><td><?php echo esc_html( $ref ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $user_id ) : ?>
				<tr><th><?php esc_html_e( 'User ID', 'fforms' ); ?></th><td>
					<?php
					// The id comes from the client and grants nothing; link it only when it resolves.
					$user = get_userdata( $user_id );
					if ( $user ) :
						?>
						<a href="<?php echo esc_url( (string) get_edit_user_link( $user_id ) ); ?>"><?php echo esc_html( $user->user_login ); ?></a>
					<?php else : ?>
						<?php echo esc_html( (string) $user_id ); ?>
					<?php endif; ?>
				</td></tr>
			<?php endif; ?>
			<?php foreach ( is_array( $custom ) ? $custom : array() as $key => $value ) : ?>
				<tr><th><?php echo esc_html( (string) $key ); ?></th><td><?php echo nl2br( esc_html( self::stringify( $value ) ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( is_array( $meta ) && array() !== $meta ) : ?>
				<tr><th><?php esc_html_e( 'Meta', 'fforms' ); ?></th><td><pre style="margin:0;white-space:pre-wrap"><?php echo esc_html( (string) wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
			<?php endif; ?>
		</tbody></table>
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
		$type_column = 'taxonomy-' . Form_Types::TAXONOMY;
		$rebuilt     = array(
			'cb'             => $columns['cb'] ?? '<input type="checkbox" />',
			'title'          => __( 'Submission', 'fforms' ),
			'fforms_form'    => __( 'Form', 'fforms' ),
		);
		// Core renders taxonomy-* columns itself; keep the key it generated.
		if ( isset( $columns[ $type_column ] ) ) {
			$rebuilt[ $type_column ] = __( 'Form type', 'fforms' );
		}
		$rebuilt['fforms_status']  = __( 'Status', 'fforms' );
		$rebuilt['fforms_preview'] = __( 'Data', 'fforms' );
		$rebuilt['date']           = $columns['date'] ?? __( 'Date', 'fforms' );

		return $rebuilt;
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

	/**
	 * The entries list filters by type term (the fform_type dropdown); `form_ref`
	 * has no control of its own and only backs the "View submissions" row action
	 * for forms that have no term yet.
	 */
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

	/**
	 * Where a form's submissions live: its own type term, falling back to the
	 * form-ref filter for forms that have no term yet (drafts, or entries saved
	 * before types).
	 */
	public static function entries_url_for_form( int $post_id ): string {
		$slug = $post_id ? Form_Types::slug_for_form( $post_id ) : '';
		return '' !== $slug
			? Form_Types::entries_url( $slug )
			: add_query_arg( array( 'post_type' => self::ENTRY, 'form_ref' => 'post:' . $post_id ), admin_url( 'edit.php' ) );
	}

	public static function entries_count_for_form( int $post_id ): int {
		if ( ! $post_id ) {
			return 0;
		}
		$args = array( 'post_type' => self::ENTRY, 'post_status' => 'private', 'posts_per_page' => 1, 'fields' => 'ids' );
		$slug = Form_Types::slug_for_form( $post_id );
		if ( '' !== $slug ) {
			$args['tax_query'] = array( array( 'taxonomy' => Form_Types::TAXONOMY, 'field' => 'slug', 'terms' => $slug ) );
		} else {
			$args['meta_key']   = '_fforms_form_id';
			$args['meta_value'] = $post_id;
		}
		return (int) ( new \WP_Query( $args ) )->found_posts;
	}

	/** @param array<string, string> $actions */
	public static function add_view_entries_row_action( array $actions, WP_Post $post ): array {
		if ( self::FORM === $post->post_type && current_user_can( 'manage_options' ) ) {
			$url = self::entries_url_for_form( $post->ID );
			$actions['fforms_view_entries'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View submissions', 'fforms' ) . '</a>';
		}
		return $actions;
	}

	public static function stringify( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'fforms' ) : __( 'No', 'fforms' );
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
