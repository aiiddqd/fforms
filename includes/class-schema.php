<?php
/**
 * Form schema normalization and submission validation.
 *
 * @package FForms
 */

namespace FForms;

use WP_Error;

final class Schema {
	private const FIELD_TYPES = array( 'text', 'textarea', 'email', 'tel', 'url', 'number', 'select', 'radio', 'checkbox', 'hidden' );

	public static function is_supported_type( string $type ): bool {
		return in_array( $type, self::FIELD_TYPES, true );
	}

	/**
	 * Return the starter contact form schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'fields' => array(
				array( 'name' => 'name', 'label' => __( 'Имя', 'fforms' ), 'type' => 'text', 'required' => true ),
				array( 'name' => 'email', 'label' => __( 'Email', 'fforms' ), 'type' => 'email', 'required' => true ),
				array( 'name' => 'message', 'label' => __( 'Сообщение', 'fforms' ), 'type' => 'textarea', 'required' => true ),
			),
		);
	}

	public static function sanitize_json( mixed $value ): string {
		$decoded = is_array( $value ) ? $value : json_decode( (string) $value, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = self::defaults();
		}

		return (string) wp_json_encode( self::normalize( $decoded ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	/**
	 * @param mixed $schema Stored array or JSON string.
	 * @return array{fields: array<int, array<string, mixed>>}
	 */
	public static function normalize( mixed $schema ): array {
		if ( is_string( $schema ) ) {
			$schema = json_decode( $schema, true );
		}
		if ( ! is_array( $schema ) ) {
			$schema = self::defaults();
		}

		$raw_fields = isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : $schema;
		$fields     = array();
		$seen       = array();

		foreach ( array_slice( $raw_fields, 0, 50 ) as $raw_field ) {
			if ( ! is_array( $raw_field ) ) {
				continue;
			}
			$name = sanitize_key( (string) ( $raw_field['name'] ?? '' ) );
			$type = sanitize_key( (string) ( $raw_field['type'] ?? 'text' ) );
			if ( '' === $name || isset( $seen[ $name ] ) || ! self::is_supported_type( $type ) ) {
				continue;
			}

			$seen[ $name ] = true;
			$field         = array(
				'name'        => $name,
				'label'       => sanitize_text_field( (string) ( $raw_field['label'] ?? $name ) ),
				'type'        => $type,
				'required'    => ! empty( $raw_field['required'] ),
				'placeholder' => sanitize_text_field( (string) ( $raw_field['placeholder'] ?? '' ) ),
			);
			if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$field['options'] = self::normalize_options( $raw_field['options'] ?? array() );
			}
			if ( isset( $raw_field['max_length'] ) ) {
				$field['max_length'] = min( 10000, max( 1, absint( $raw_field['max_length'] ) ) );
			}
			$fields[] = $field;
		}

		return array() === $fields ? self::defaults() : array( 'fields' => $fields );
	}

	/**
	 * @param mixed $options Raw options.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function normalize_options( mixed $options ): array {
		if ( ! is_array( $options ) ) {
			return array();
		}
		$normalized = array();
		foreach ( array_slice( $options, 0, 100 ) as $option ) {
			if ( is_array( $option ) ) {
				$value = sanitize_text_field( (string) ( $option['value'] ?? '' ) );
				$label = sanitize_text_field( (string) ( $option['label'] ?? $value ) );
			} else {
				$value = sanitize_text_field( (string) $option );
				$label = $value;
			}
			if ( '' !== $value ) {
				$normalized[] = array( 'value' => $value, 'label' => $label );
			}
		}
		return $normalized;
	}

	/**
	 * @param array<string, mixed> $input Submitted fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate_submission( array $schema, array $input ): array|WP_Error {
		$schema = self::normalize( $schema );
		$data   = array();
		$errors = array();

		foreach ( $schema['fields'] as $field ) {
			$name  = $field['name'];
			$value = $input[ $name ] ?? null;
			if ( self::is_empty( $value ) ) {
				if ( $field['required'] ) {
					$errors[ $name ] = sprintf( __( 'Поле «%s» обязательно.', 'fforms' ), $field['label'] );
				}
				$data[ $name ] = 'checkbox' === $field['type'] && ! empty( $field['options'] ) ? array() : '';
				continue;
			}

			$sanitized = self::sanitize_value( $field, $value );
			if ( is_wp_error( $sanitized ) ) {
				$errors[ $name ] = $sanitized->get_error_message();
			} else {
				$data[ $name ] = $sanitized;
			}
		}

		if ( array() !== $errors ) {
			return new WP_Error( 'fforms_validation_failed', __( 'Проверьте заполненные поля.', 'fforms' ), array( 'status' => 422, 'fields' => $errors ) );
		}
		return $data;
	}

	private static function sanitize_value( array $field, mixed $value ): mixed {
		$type       = $field['type'];
		$max_length = (int) ( $field['max_length'] ?? ( 'textarea' === $type ? 10000 : 2000 ) );

		if ( 'checkbox' === $type && ! empty( $field['options'] ) ) {
			$values  = is_array( $value ) ? $value : array( $value );
			$allowed = wp_list_pluck( $field['options'], 'value' );
			return array_values( array_intersect( array_map( 'sanitize_text_field', $values ), $allowed ) );
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return new WP_Error( 'fforms_invalid_value', __( 'Некорректное значение поля.', 'fforms' ) );
		}

		$value = self::truncate( (string) $value, $max_length );
		switch ( $type ) {
			case 'email':
				$value = sanitize_email( $value );
				return is_email( $value ) ? $value : new WP_Error( 'fforms_invalid_email', __( 'Укажите корректный email.', 'fforms' ) );
			case 'url':
				$value = esc_url_raw( $value, array( 'http', 'https' ) );
				return '' !== $value ? $value : new WP_Error( 'fforms_invalid_url', __( 'Укажите корректный URL.', 'fforms' ) );
			case 'number':
				return is_numeric( $value ) ? $value : new WP_Error( 'fforms_invalid_number', __( 'Укажите число.', 'fforms' ) );
			case 'select':
			case 'radio':
				$allowed = wp_list_pluck( $field['options'] ?? array(), 'value' );
				$value   = sanitize_text_field( $value );
				return in_array( $value, $allowed, true ) ? $value : new WP_Error( 'fforms_invalid_option', __( 'Выберите допустимый вариант.', 'fforms' ) );
			case 'checkbox':
				return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
			case 'textarea':
				return sanitize_textarea_field( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	private static function is_empty( mixed $value ): bool {
		return null === $value || '' === $value || ( is_array( $value ) && array() === $value );
	}

	private static function truncate( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
