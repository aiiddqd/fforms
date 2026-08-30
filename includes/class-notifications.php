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
	public static function send( Form_Ref $form, int $entry_id, array $data ): bool {
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

			$sent = wp_mail( $recipients, $subject, implode( "\n", $lines ) );
		}
		self::send_autoreply( $form, $data );
		return $sent;
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
