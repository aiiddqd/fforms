<?php
/**
 * The built-in zero-config form backing POST /fforms/v1/main.
 *
 * It exists only in memory: no post, no admin editor, no block. Keeping it a
 * regular Form_Ref means the existing /forms, /forms/{key} and /forms/{key}/schema
 * routes serve it without any special-casing.
 *
 * @package FForms
 */

namespace FForms\Registry;

use FForms\Form_Ref;
use FForms\Schema;

final class Main_Form {
	public const KEY = 'main';

	private static ?Form_Ref $ref = null;

	public static function ref(): Form_Ref {
		if ( null === self::$ref ) {
			self::$ref = new Form_Ref(
				post_id: 0,
				key: self::KEY,
				title: __( 'Main form (API)', 'fforms' ),
				schema: Schema::normalize( array( 'fields' => self::fields() ) ),
				success_message: __( 'Thank you! Your submission has been sent.', 'fforms' ),
				origins: self::origins(),
				notifications: self::notifications(),
				source: 'builtin',
				type: null
			);
		}

		return self::$ref;
	}

	/**
	 * At least one of email, phone or message must carry content, so every field
	 * is optional on its own.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function fields(): array {
		return array(
			array( 'name' => 'name', 'label' => __( 'Name', 'fforms' ), 'type' => 'text', 'required' => false ),
			array( 'name' => 'email', 'label' => __( 'Email', 'fforms' ), 'type' => 'email', 'required' => false ),
			array( 'name' => 'phone', 'label' => __( 'Phone', 'fforms' ), 'type' => 'tel', 'required' => false ),
			array( 'name' => 'message', 'label' => __( 'Message', 'fforms' ), 'type' => 'textarea', 'required' => false ),
		);
	}

	/** @return array<int, string> */
	private static function origins(): array {
		$settings = \FForms\Settings::get();
		return is_array( $settings['main_form_origins'] ?? null ) ? $settings['main_form_origins'] : array();
	}

	/** @return array<string, mixed> */
	private static function notifications(): array {
		$settings = \FForms\Settings::get();
		return array(
			'enabled'               => ! empty( $settings['main_form_notifications'] ),
			'to'                    => (string) ( $settings['main_form_notification_to'] ?? '' ),
			'subject'               => '',
			'autoreply_enabled'     => false,
			'autoreply_email_field' => 'email',
			'autoreply_subject'     => '',
			'autoreply_message'     => '',
		);
	}

	/**
	 * Invalidate the memoized ref after settings change within one request.
	 */
	public static function flush(): void {
		self::$ref = null;
	}
}
