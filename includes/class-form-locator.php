<?php
/**
 * Resolves a post ID, a code-form key, the built-in main form key or a
 * fform_type term slug owned by a form to a single Form_Ref shape.
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

		$key = sanitize_key( (string) $ref );
		if ( Registry\Main_Form::KEY === $key ) {
			return Registry\Main_Form::ref();
		}

		$code = Registry\Code_Forms::get( $key );
		if ( $code ) {
			return $code;
		}

		$form_id = Form_Types::form_id_for_slug( $key );
		if ( $form_id > 0 ) {
			return self::resolve_post( $form_id );
		}

		return new WP_Error( 'fforms_form_not_found', __( 'Форма не найдена.', 'fforms' ), array( 'status' => 404 ) );
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
			source: 'post',
			type: Form_Types::slug_for_form( $post_id ) ?: null
		);
	}

}
