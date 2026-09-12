<?php
/**
 * Submission type taxonomy: business meaning of an entry, independent of which
 * form received it.
 *
 * A CPT form also owns a term, so the term slug doubles as a stable string key
 * for that form — headless clients no longer need a post ID that differs
 * between dev, stage and prod.
 *
 * @package FForms
 */

namespace FForms;

use WP_Error;
use WP_Post;
use WP_Term;

final class Form_Types {
	public const TAXONOMY = 'fform_type';

	/** Term meta pointing back at the CPT form that owns the term. */
	public const TERM_FORM_ID = '_fforms_form_id';

	/** Term meta marking a term the API created on its own. */
	private const TERM_AUTOCREATED = '_fforms_autocreated';

	private const SLUG_PATTERN = '/^[a-z0-9_-]{1,32}$/';

	public static function boot(): void {
		add_action( 'wp_after_insert_post', array( self::class, 'sync_form_term' ), 20, 3 );
		add_action( 'before_delete_post', array( self::class, 'detach_form_term' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( self::class, 'render_term_form_link' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'render_entries_type_filter' ) );
		add_action( 'admin_menu', array( self::class, 'admin_menu' ), 11 );
	}

	public static function register(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( Post_Types::ENTRY, Post_Types::FORM ),
			array(
				'labels' => array(
					'name'          => __( 'Типы заявок', 'fforms' ),
					'singular_name' => __( 'Тип заявки', 'fforms' ),
					'menu_name'     => __( 'Типы заявок', 'fforms' ),
					'edit_item'     => __( 'Редактировать тип заявки', 'fforms' ),
					'add_new_item'  => __( 'Добавить тип заявки', 'fforms' ),
					'search_items'  => __( 'Искать типы заявок', 'fforms' ),
					'all_items'     => __( 'Все типы заявок', 'fforms' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				// The screen is added once by self::admin_menu(); core would otherwise
				// add it separately for every object type sharing the FForms menu.
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'show_in_nav_menus'  => false,
				'hierarchical'       => false,
				'show_admin_column'  => true,
				'query_var'          => self::TAXONOMY,
				'rewrite'            => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'manage_options',
				),
			)
		);

		register_term_meta( self::TAXONOMY, self::TERM_FORM_ID, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false ) );
		register_term_meta( self::TAXONOMY, self::TERM_AUTOCREATED, array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => false ) );
	}

	/** One "Типы заявок" entry right after "Ответы". */
	public static function admin_menu(): void {
		add_submenu_page(
			'fforms',
			__( 'Типы заявок', 'fforms' ),
			__( 'Типы заявок', 'fforms' ),
			'manage_options',
			'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=' . Post_Types::ENTRY,
			'',
			3
		);
	}

	/**
	 * Turn a client-supplied value into a term slug.
	 *
	 * @return string|WP_Error Empty string when nothing was supplied.
	 */
	public static function normalize( mixed $value ): string|WP_Error {
		$raw = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $raw ) {
			return '';
		}

		$slug = sanitize_key( str_replace( ' ', '_', $raw ) );
		if ( '' === $slug || ! preg_match( self::SLUG_PATTERN, $slug ) ) {
			return new WP_Error(
				'fforms_invalid_form_type',
				__( 'Некорректный тип заявки: допустимы латиница, цифры, дефис и подчёркивание, до 32 символов.', 'fforms' ),
				array( 'status' => 422 )
			);
		}

		return $slug;
	}

	public static function get_term( string $slug ): ?WP_Term {
		$term = get_term_by( 'slug', $slug, self::TAXONOMY );
		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Resolve a slug to a term, creating it when the site allows autocreation.
	 *
	 * Returns null when the term neither exists nor may be created — the entry
	 * is still saved, just without a type.
	 */
	public static function upsert( string $slug ): WP_Term|WP_Error|null {
		$term = self::get_term( $slug );
		if ( $term ) {
			return $term;
		}

		if ( self::is_strict() ) {
			return new WP_Error(
				'fforms_unknown_form_type',
				__( 'Неизвестный тип заявки.', 'fforms' ),
				array( 'status' => 422 )
			);
		}

		if ( self::autocreated_count() >= self::max_types() ) {
			return null;
		}

		$created = wp_insert_term( $slug, self::TAXONOMY, array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			// A parallel request may have created it in between.
			return self::get_term( $slug );
		}

		update_term_meta( (int) $created['term_id'], self::TERM_AUTOCREATED, 1 );
		return self::get_term( $slug );
	}

	/**
	 * Assign the type to an entry. The raw value is kept when no term was made,
	 * so an over-limit submission still records what the client sent.
	 */
	public static function assign_to_entry( int $entry_id, string $slug, ?WP_Term $term ): void {
		if ( $term ) {
			wp_set_object_terms( $entry_id, array( $term->term_id ), self::TAXONOMY, false );
			return;
		}
		if ( '' !== $slug ) {
			update_post_meta( $entry_id, '_fforms_form_type_raw', $slug );
		}
	}

	public static function entry_type_slug( int $entry_id ): string {
		$terms = wp_get_object_terms( $entry_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_array( $terms ) && array() !== $terms ) {
			return (string) $terms[0];
		}
		return (string) get_post_meta( $entry_id, '_fforms_form_type_raw', true );
	}

	/** The form a term points at, or 0 when the term is a plain submission type. */
	public static function form_id_for_slug( string $slug ): int {
		$term = self::get_term( $slug );
		return $term ? (int) get_term_meta( $term->term_id, self::TERM_FORM_ID, true ) : 0;
	}

	/** The term slug owned by a CPT form, if any. */
	public static function slug_for_form( int $form_id ): string {
		$terms = wp_get_object_terms( $form_id, self::TAXONOMY, array( 'fields' => 'all' ) );
		if ( ! is_array( $terms ) ) {
			return '';
		}
		foreach ( $terms as $term ) {
			if ( (int) get_term_meta( $term->term_id, self::TERM_FORM_ID, true ) === $form_id ) {
				return $term->slug;
			}
		}
		return '';
	}

	/**
	 * Publishing a form gives it a term whose slug stays stable across renames.
	 */
	public static function sync_form_term( int $post_id, WP_Post $post, bool $update ): void {
		if ( Post_Types::FORM !== $post->post_type || wp_is_post_revision( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}

		$existing = self::slug_for_form( $post_id );
		$title    = get_the_title( $post ) ?: $post->post_name;
		if ( '' !== $existing ) {
			$term = self::get_term( $existing );
			if ( $term && $term->name !== $title ) {
				wp_update_term( $term->term_id, self::TAXONOMY, array( 'name' => $title ) );
			}
			return;
		}

		$slug = self::unique_slug( (string) $post->post_name ?: 'form-' . $post_id );
		$term = self::get_term( $slug );
		if ( ! $term ) {
			$created = wp_insert_term( $title, self::TAXONOMY, array( 'slug' => $slug ) );
			if ( is_wp_error( $created ) ) {
				return;
			}
			$term = self::get_term( $slug );
		}
		if ( ! $term ) {
			return;
		}

		update_term_meta( $term->term_id, self::TERM_FORM_ID, $post_id );
		delete_term_meta( $term->term_id, self::TERM_AUTOCREATED );
		wp_set_object_terms( $post_id, array( $term->term_id ), self::TAXONOMY, false );
	}

	/**
	 * Deleting a form keeps the term so entries stay classified; only the
	 * back-reference goes away.
	 */
	public static function detach_form_term( int $post_id ): void {
		if ( Post_Types::FORM !== get_post_type( $post_id ) ) {
			return;
		}
		$slug = self::slug_for_form( $post_id );
		$term = '' === $slug ? null : self::get_term( $slug );
		if ( $term ) {
			delete_term_meta( $term->term_id, self::TERM_FORM_ID );
		}
	}

	public static function render_term_form_link( WP_Term $term ): void {
		$form_id = (int) get_term_meta( $term->term_id, self::TERM_FORM_ID, true );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Форма', 'fforms' ); ?></th>
			<td>
				<?php if ( $form_id && Post_Types::FORM === get_post_type( $form_id ) ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $form_id ) ); ?>"><?php echo esc_html( get_the_title( $form_id ) ); ?></a>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Формы с этим типом больше нет — заявки сохраняют классификацию.', 'fforms' ); ?></p>
				<?php endif; ?>
				<p class="description">
					<a href="<?php echo esc_url( self::entries_url( $term->slug ) ); ?>"><?php esc_html_e( 'Смотреть заявки этого типа', 'fforms' ); ?></a>
				</p>
			</td>
		</tr>
		<?php
	}

	public static function render_entries_type_filter(): void {
		global $typenow;
		if ( Post_Types::ENTRY !== $typenow ) {
			return;
		}
		$terms = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) || array() === $terms ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filter.
		$current = isset( $_GET[ self::TAXONOMY ] ) ? sanitize_key( wp_unslash( $_GET[ self::TAXONOMY ] ) ) : '';
		?>
		<label class="screen-reader-text" for="fforms-filter-type"><?php esc_html_e( 'Фильтр по типу заявки', 'fforms' ); ?></label>
		<select id="fforms-filter-type" name="<?php echo esc_attr( self::TAXONOMY ); ?>">
			<option value=""><?php esc_html_e( 'Все типы заявок', 'fforms' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function entries_url( string $slug ): string {
		return add_query_arg(
			array( 'post_type' => Post_Types::ENTRY, self::TAXONOMY => $slug ),
			admin_url( 'edit.php' )
		);
	}

	public static function is_strict(): bool {
		return ! empty( Settings::get()['form_types_strict'] );
	}

	private static function max_types(): int {
		return max( 1, (int) apply_filters( 'fforms_max_form_types', 50 ) );
	}

	private static function autocreated_count(): int {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array( array( 'key' => self::TERM_AUTOCREATED, 'compare' => 'EXISTS' ) ),
			)
		);
		return is_array( $terms ) ? count( $terms ) : 0;
	}

	/** Mirror core's numeric-suffix behaviour for an already taken slug. */
	private static function unique_slug( string $base ): string {
		$base = sanitize_key( $base ) ?: 'form';
		$base = substr( $base, 0, 32 );
		if ( ! self::get_term( $base ) ) {
			return $base;
		}
		for ( $suffix = 2; $suffix < 100; $suffix++ ) {
			$candidate = substr( $base, 0, 32 - strlen( (string) $suffix ) - 1 ) . '-' . $suffix;
			if ( ! self::get_term( $candidate ) ) {
				return $candidate;
			}
		}
		return $base . '-' . wp_generate_password( 4, false, false );
	}
}
