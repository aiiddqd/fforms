<?php
/**
 * Reads schemas from the canonical block tree with a legacy compatibility cache.
 *
 * @package FForms
 */

namespace FForms\Schema;

use FForms\Post_Types;
use FForms\Schema;
use WP_Error;

final class Schema_Repository {
	/** @return array{fields: array<int, array<string, mixed>>}|WP_Error */
	public static function for_form( int $form_id ): array|WP_Error {
		$form = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type ) {
			return new WP_Error( 'fforms_form_not_found', __( 'Форма не найдена.', 'fforms' ) );
		}

		if ( ! Schema_Compiler::has_form_block( $form->post_content ) ) {
			return Schema::normalize( (string) get_post_meta( $form_id, '_fforms_schema', true ) );
		}

		$hash = hash( 'sha256', $form->post_content );
		if ( $hash === (string) get_post_meta( $form_id, '_fforms_schema_hash', true ) ) {
			$cached = json_decode( (string) get_post_meta( $form_id, '_fforms_schema', true ), true );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$schema = Schema_Compiler::compile( $form );
		if ( ! is_wp_error( $schema ) ) {
			self::store_cache( $form_id, $schema, $hash );
		}
		return $schema;
	}

	/** @param array{fields: array<int, array<string, mixed>>} $schema */
	public static function store_cache( int $form_id, array $schema, ?string $hash = null ): void {
		update_post_meta( $form_id, '_fforms_schema', (string) wp_json_encode( $schema, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $form_id, '_fforms_schema_hash', $hash ?: hash( 'sha256', (string) get_post_field( 'post_content', $form_id ) ) );
	}

	public static function invalidate( int $form_id ): void {
		delete_post_meta( $form_id, '_fforms_schema_hash' );
	}
}
