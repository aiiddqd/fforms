<?php
/**
 * Compiles the canonical Gutenberg block tree into a submission schema.
 *
 * @package FForms
 */

namespace FForms\Schema;

use FForms\Schema;
use WP_Error;
use WP_Post;

final class Schema_Compiler {
	private const FORM_BLOCK            = 'fforms/form';
	private const HEADLESS_SCHEMA_BLOCK = 'fforms/headless-schema';
	private const SUBMIT_BLOCK          = 'fforms/submit';

	/**
	 * @return array{fields: array<int, array<string, mixed>>}|WP_Error
	 */
	public static function compile( WP_Post|string $form ): array|WP_Error {
		$content = $form instanceof WP_Post ? $form->post_content : $form;
		$blocks  = parse_blocks( $content );
		$root    = self::find_root( $blocks );
		if ( ! $root ) {
			return new WP_Error( 'fforms_no_form_block', __( 'Форма должна содержать корневой блок FForms.', 'fforms' ) );
		}

		$fields = array();
		$names  = array();
		$errors = array();
		$submit = 0;
		self::walk( $root['innerBlocks'] ?? array(), $fields, $names, $errors, $submit );

		if ( self::FORM_BLOCK === $root['blockName'] && 1 !== $submit ) {
			$errors[] = __( 'В форме должна быть ровно одна кнопка отправки.', 'fforms' );
		}
		if ( array() === $fields ) {
			$errors[] = __( 'Добавьте хотя бы одно поле формы.', 'fforms' );
		}
		if ( array() !== $errors ) {
			return new WP_Error( 'fforms_invalid_block_schema', __( 'Структура формы содержит ошибки.', 'fforms' ), array( 'status' => 422, 'errors' => $errors ) );
		}

		return array( 'fields' => $fields );
	}

	public static function has_form_block( string $content ): bool {
		return (bool) self::find_named_root( parse_blocks( $content ), self::FORM_BLOCK );
	}

	/**
	 * Whether the post contains either editor representation of an FForms schema.
	 */
	public static function has_schema_block( string $content ): bool {
		return (bool) self::find_root( parse_blocks( $content ) );
	}

	/** @param array<int, array<string, mixed>> $blocks */
	private static function find_root( array $blocks ): array|false {
		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'] ?? '', array( self::FORM_BLOCK, self::HEADLESS_SCHEMA_BLOCK ), true ) ) {
				return $block;
			}
		}
		return false;
	}

	/** @param array<int, array<string, mixed>> $blocks */
	private static function find_named_root( array $blocks, string $name ): array|false {
		foreach ( $blocks as $block ) {
			if ( $name === ( $block['blockName'] ?? '' ) ) {
				return $block;
			}
		}
		return false;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<string, bool> $names
	 * @param array<int, string> $errors
	 */
	private static function walk( array $blocks, array &$fields, array &$names, array &$errors, int &$submit ): void {
		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( self::SUBMIT_BLOCK === $name ) {
				++$submit;
			} elseif ( str_starts_with( $name, 'fforms/field-' ) ) {
				$field = self::field_from_block( $name, $block['attrs'] ?? array(), $errors );
				if ( $field ) {
					if ( isset( $names[ $field['name'] ] ) ) {
						$errors[] = sprintf( __( 'Имя поля «%s» повторяется.', 'fforms' ), $field['name'] );
					} else {
						$names[ $field['name'] ] = true;
						$fields[]                = $field;
					}
				}
			}
			self::walk( $block['innerBlocks'] ?? array(), $fields, $names, $errors, $submit );
		}
	}

	/**
	 * @param array<string, mixed> $attributes
	 * @param array<int, string> $errors
	 * @return array<string, mixed>|false
	 */
	private static function field_from_block( string $block_name, array $attributes, array &$errors ): array|false {
		$type     = substr( $block_name, strlen( 'fforms/field-' ) );
		$raw_name = (string) ( $attributes['name'] ?? '' );
		$name     = sanitize_key( $raw_name );
		if ( ! Schema::is_supported_type( $type ) || '' === $name || $name !== $raw_name ) {
			$errors[] = __( 'У каждого поля должно быть корректное уникальное имя.', 'fforms' );
			return false;
		}

		$field = array(
			'field_id'    => sanitize_html_class( (string) ( $attributes['fieldId'] ?? $name ) ),
			'name'        => $name,
			'label'       => sanitize_text_field( (string) ( $attributes['label'] ?? $name ) ),
			'type'        => $type,
			'required'    => ! empty( $attributes['required'] ),
			'placeholder' => sanitize_text_field( (string) ( $attributes['placeholder'] ?? '' ) ),
		);
		if ( '' === $field['field_id'] ) {
			$field['field_id'] = $name;
		}
		if ( isset( $attributes['maxLength'] ) && absint( $attributes['maxLength'] ) ) {
			$field['max_length'] = min( 10000, absint( $attributes['maxLength'] ) );
		}
		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
			$options = Schema::normalize_options( $attributes['options'] ?? array() );
			if ( array() === $options || count( $options ) !== count( (array) ( $attributes['options'] ?? array() ) ) ) {
				$errors[] = sprintf( __( 'Поле «%s» должно иметь корректные варианты.', 'fforms' ), $field['label'] );
				return false;
			}
			$field['options'] = $options;
		}

		return $field;
	}
}
