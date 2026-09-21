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

final class Main_Form {
	public const KEY = 'main';

	private static ?Form_Ref $ref = null;

	public static function ref(): Form_Ref {
		if ( null === self::$ref ) {
			self::$ref = new Form_Ref(
				post_id: 0,
				key: self::KEY,
				title: __( 'Main form (API)', 'fforms' ),
				// No schema at all: /main stores whatever keys the client sends, so
				// there is nothing to declare, validate against or keep in sync.
				schema: array( 'fields' => array() ),
				success_message: __( 'Thank you! Your submission has been sent.', 'fforms' ),
				origins: self::origins(),
				notifications: self::notifications(),
				source: 'builtin',
				type: null
			);
		}

		return self::$ref;
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
			// The built-in form never has its own override; Notifications resolves
			// the shared setting and then the dynamic WordPress admin address.
			'to'                    => '',
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
