<?php
/**
 * In-memory registry of code-registered (headless) forms.
 *
 * @package FForms
 */

namespace FForms\Registry;

use FForms\Form_Ref;
use FForms\Schema;
use WP_Error;

final class Code_Forms {
	private const KEY_PATTERN = '/^[a-z0-9_]{1,32}$/';

	/** @var array<string, Form_Ref> */
	private static array $forms = array();

	public static function boot(): void {
		add_action( 'init', array( self::class, 'fire_registration' ), 5 );
	}

	public static function fire_registration(): void {
		do_action( 'fforms_register_forms' );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function register( string $key, array $args ): true|WP_Error {
		$key = sanitize_key( $key );
		if ( '' === $key || ! preg_match( self::KEY_PATTERN, $key ) ) {
			return new WP_Error( 'fforms_invalid_key', __( 'Некорректный ключ формы.', 'fforms' ) );
		}
		if ( isset( self::$forms[ $key ] ) ) {
			return new WP_Error( 'fforms_key_exists', __( 'Форма с таким ключом уже зарегистрирована.', 'fforms' ) );
		}

		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'fforms_title_required', __( 'Укажите название формы.', 'fforms' ) );
		}

		$raw_fields = $args['fields'] ?? array();
		if ( ! is_array( $raw_fields ) || array() === $raw_fields || ! self::has_valid_field( $raw_fields ) ) {
			return new WP_Error( 'fforms_invalid_schema', __( 'Укажите хотя бы одно валидное поле формы.', 'fforms' ) );
		}

		$notifications = is_array( $args['notifications'] ?? null ) ? $args['notifications'] : array();
		$autoreply     = is_array( $args['autoreply'] ?? null ) ? $args['autoreply'] : array();

		self::$forms[ $key ] = new Form_Ref(
			post_id: 0,
			key: $key,
			title: $title,
			schema: Schema::normalize( array( 'fields' => $raw_fields ) ),
			success_message: sanitize_text_field( (string) ( $args['success_message'] ?? '' ) ) ?: __( 'Спасибо! Форма отправлена.', 'fforms' ),
			origins: self::sanitize_origins( $args['origins'] ?? array() ),
			notifications: array(
				'enabled'               => ! empty( $notifications['enabled'] ),
				'to'                    => sanitize_text_field( (string) ( $notifications['to'] ?? '' ) ),
				'subject'               => sanitize_text_field( (string) ( $notifications['subject'] ?? '' ) ),
				'autoreply_enabled'     => ! empty( $autoreply['enabled'] ),
				'autoreply_email_field' => sanitize_key( (string) ( $autoreply['email_field'] ?? 'email' ) ),
				'autoreply_subject'     => sanitize_text_field( (string) ( $autoreply['subject'] ?? '' ) ),
				'autoreply_message'     => sanitize_textarea_field( (string) ( $autoreply['message'] ?? '' ) ),
			),
			source: 'code'
		);

		return true;
	}

	public static function get( string $key ): ?Form_Ref {
		return self::$forms[ sanitize_key( $key ) ] ?? null;
	}

	public static function exists( string $key ): bool {
		return isset( self::$forms[ sanitize_key( $key ) ] );
	}

	/** @return array<string, Form_Ref> */
	public static function all(): array {
		return self::$forms;
	}

	/**
	 * Mirrors Schema::normalize()'s per-field drop rules just enough to tell
	 * whether normalization would silently fall back to Schema::defaults().
	 *
	 * @param array<int, mixed> $raw_fields
	 */
	private static function has_valid_field( array $raw_fields ): bool {
		$seen = array();
		foreach ( array_slice( $raw_fields, 0, 50 ) as $raw_field ) {
			if ( ! is_array( $raw_field ) ) {
				continue;
			}
			$name = sanitize_key( (string) ( $raw_field['name'] ?? '' ) );
			$type = sanitize_key( (string) ( $raw_field['type'] ?? 'text' ) );
			if ( '' === $name || isset( $seen[ $name ] ) || ! Schema::is_supported_type( $type ) ) {
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * @param mixed $origins
	 * @return array<int, string>
	 */
	private static function sanitize_origins( mixed $origins ): array {
		if ( ! is_array( $origins ) ) {
			return array();
		}
		$clean = array();
		foreach ( $origins as $origin ) {
			$url = esc_url_raw( (string) $origin, array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$clean[] = untrailingslashit( $url );
			}
		}
		return array_values( array_unique( $clean ) );
	}
}
