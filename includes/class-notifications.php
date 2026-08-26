<?php
/**
 * Entry notification emails.
 *
 * @package FForms
 */

namespace FForms;

final class Notifications {
	/**
	 * @param array<string, mixed> $data Sanitized submission data.
	 */
	public static function send( int $form_id, int $entry_id, array $data ): bool {
		if ( empty( Settings::get()['notifications'] ) ) {
			return false;
		}

		$raw_recipients = (string) get_post_meta( $form_id, '_fforms_notification_to', true );
		$recipients     = array_filter( array_map( 'sanitize_email', preg_split( '/\s*,\s*/', $raw_recipients ) ?: array() ), 'is_email' );
		if ( array() === $recipients ) {
			$recipients = array( sanitize_email( (string) get_option( 'admin_email' ) ) );
		}

		$subject = (string) get_post_meta( $form_id, '_fforms_notification_subject', true );
		if ( '' === $subject ) {
			$subject = sprintf( __( 'Новый ответ: %s', 'fforms' ), get_the_title( $form_id ) );
		}

		$lines = array( sprintf( __( 'Форма: %s', 'fforms' ), get_the_title( $form_id ) ), sprintf( __( 'Ответ #%d', 'fforms' ), $entry_id ), '' );
		foreach ( $data as $key => $value ) {
			$lines[] = sprintf( '%s: %s', $key, Post_Types::stringify( $value ) );
		}

		$sent = wp_mail( $recipients, $subject, implode( "\n", $lines ) );
		self::send_autoreply( $form_id, $data );
		return $sent;
	}

	private static function send_autoreply( int $form_id, array $data ): void {
		if ( ! get_post_meta( $form_id, '_fforms_autoreply_enabled', true ) ) {
			return;
		}
		$field   = sanitize_key( (string) get_post_meta( $form_id, '_fforms_autoreply_email_field', true ) ) ?: 'email';
		$address = sanitize_email( (string) ( $data[ $field ] ?? '' ) );
		if ( ! is_email( $address ) ) {
			return;
		}
		$subject = (string) get_post_meta( $form_id, '_fforms_autoreply_subject', true );
		$message = (string) get_post_meta( $form_id, '_fforms_autoreply_message', true );
		wp_mail( $address, $subject ?: sprintf( __( 'Мы получили ваше сообщение — %s', 'fforms' ), get_bloginfo( 'name' ) ), $message ?: __( 'Спасибо! Мы получили ваше сообщение и скоро ответим.', 'fforms' ) );
	}
}
