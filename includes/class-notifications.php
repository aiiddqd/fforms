<?php
/**
 * Entry notification emails.
 *
 * @package FForms
 */

namespace FForms;

final class Notifications {
	/**
	 * @param array<string, mixed> $data   Sanitized submission data.
	 * @param array<string, mixed> $extras Off-schema data: type, custom fields, meta, ref, user id.
	 */
	public static function send( Form_Ref $form, int $entry_id, array $data, array $extras = array() ): bool {
		if ( empty( Settings::get()['notifications'] ) ) {
			return false;
		}

		$sent = false;
		if ( ! empty( $form->notifications['enabled'] ) ) {
			$raw_recipients = (string) $form->notifications['to'];
			$recipients     = array_filter( array_map( 'sanitize_email', preg_split( '/\s*,\s*/', $raw_recipients ) ?: array() ), 'is_email' );
			if ( array() === $recipients ) {
				$recipients = array( sanitize_email( (string) get_option( 'admin_email' ) ) );
			}

			$subject = (string) $form->notifications['subject'];
			if ( '' === $subject ) {
				$subject = sprintf( __( 'Новый ответ: %s', 'fforms' ), $form->title );
			}

			$lines  = array( sprintf( __( 'Форма: %s', 'fforms' ), $form->title ), sprintf( __( 'Ответ #%d', 'fforms' ), $entry_id ), '' );
			$labels = wp_list_pluck( $form->schema['fields'], 'label', 'name' );
			foreach ( $data as $key => $value ) {
				$lines[] = sprintf( '%s: %s', $labels[ $key ] ?? $key, Post_Types::stringify( $value ) );
			}
			$lines = array_merge( $lines, self::extras_lines( $entry_id, $extras ) );

			$sent = wp_mail( $recipients, $subject, implode( "\n", $lines ) );
		}
		self::send_autoreply( $form, $data );
		return $sent;
	}

	/**
	 * Off-schema context goes in its own block after the form fields, so the
	 * validated part of the submission stays visually separate.
	 *
	 * @param array<string, mixed> $extras
	 * @return array<int, string>
	 */
	private static function extras_lines( int $entry_id, array $extras ): array {
		$type  = Form_Types::entry_type_slug( $entry_id );
		$lines = array();

		if ( '' !== $type ) {
			$term    = Form_Types::get_term( $type );
			$lines[] = sprintf( __( 'Тип формы: %s', 'fforms' ), $term ? $term->name : $type );
		}
		if ( '' !== (string) ( $extras['ref'] ?? '' ) ) {
			$lines[] = sprintf( __( 'Ref: %s', 'fforms' ), (string) $extras['ref'] );
		}
		if ( ! empty( $extras['user_id'] ) ) {
			$lines[] = sprintf( __( 'User ID: %d', 'fforms' ), (int) $extras['user_id'] );
		}
		foreach ( (array) ( $extras['custom'] ?? array() ) as $key => $value ) {
			$lines[] = sprintf( '%s: %s', (string) $key, Post_Types::stringify( $value ) );
		}
		if ( array() !== (array) ( $extras['meta'] ?? array() ) ) {
			$lines[] = sprintf( __( 'Meta: %s', 'fforms' ), (string) wp_json_encode( $extras['meta'], JSON_UNESCAPED_UNICODE ) );
		}

		return array() === $lines ? array() : array_merge( array( '' ), $lines );
	}

	private static function send_autoreply( Form_Ref $form, array $data ): void {
		if ( empty( $form->notifications['autoreply_enabled'] ) ) {
			return;
		}
		$field   = $form->notifications['autoreply_email_field'] ?: 'email';
		$address = sanitize_email( (string) ( $data[ $field ] ?? '' ) );
		if ( ! is_email( $address ) ) {
			return;
		}
		$subject = (string) $form->notifications['autoreply_subject'];
		$message = (string) $form->notifications['autoreply_message'];
		wp_mail( $address, $subject ?: sprintf( __( 'Мы получили ваше сообщение — %s', 'fforms' ), get_bloginfo( 'name' ) ), $message ?: __( 'Спасибо! Мы получили ваше сообщение и скоро ответим.', 'fforms' ) );
	}
}
