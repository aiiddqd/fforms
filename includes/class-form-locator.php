<?php
/**
 * Resolves a post ID or a code-form key to a single Form_Ref shape.
 *
 * @package FForms
 */

namespace FForms;

use WP_Error;

final class Form_Locator {
	public static function resolve( int|string $ref ): Form_Ref|WP_Error {
		if ( is_int( $ref ) || ( is_string( $ref ) && '' !== $ref && ctype_digit( $ref ) ) ) {
			return self::resolve_post( (int) $ref );
		}
		return self::resolve_code( (string) $ref );
	}

	private static function resolve_post( int $post_id ): Form_Ref|WP_Error {
		$post = get_post( $post_id );
		if ( ! $post || Post_Types::FORM !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'fforms_form_not_found', __( 'Форма не найдена.', 'fforms' ), array( 'status' => 404 ) );
		}

		$schema = \FForms\Schema\Schema_Repository::for_form( $post_id );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		return new Form_Ref(
			post_id: $post_id,
			key: null,
			title: get_the_title( $post ),
			schema: $schema,
			success_message: (string) get_post_meta( $post_id, '_fforms_success_message', true ) ?: __( 'Спасибо! Форма отправлена.', 'fforms' ),
			origins: array(),
			notifications: array(
				'enabled'               => (bool) get_post_meta( $post_id, '_fforms_notifications_enabled', true ),
				'to'                    => (string) get_post_meta( $post_id, '_fforms_notification_to', true ),
				'subject'               => (string) get_post_meta( $post_id, '_fforms_notification_subject', true ),
				'autoreply_enabled'     => (bool) get_post_meta( $post_id, '_fforms_autoreply_enabled', true ),
				'autoreply_email_field' => (string) get_post_meta( $post_id, '_fforms_autoreply_email_field', true ),
				'autoreply_subject'     => (string) get_post_meta( $post_id, '_fforms_autoreply_subject', true ),
				'autoreply_message'     => (string) get_post_meta( $post_id, '_fforms_autoreply_message', true ),
			),
			source: 'post'
		);
	}

	private static function resolve_code( string $key ): Form_Ref|WP_Error {
		$form = Registry\Code_Forms::get( sanitize_key( $key ) );
		return $form ?? new WP_Error( 'fforms_form_not_found', __( 'Форма не найдена.', 'fforms' ), array( 'status' => 404 ) );
	}
}
