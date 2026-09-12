<?php
/**
 * One-time migration folding the legacy `_fforms_public` toggle into `_fforms_mode`.
 *
 * @package FForms
 */

namespace FForms\Migration;

use FForms\Post_Types;

final class Mode_Migration {
	private const VERSION = '1';
	private const OPTION  = 'fforms_mode_migration_version';

	public static function boot(): void {
		add_action( 'admin_init', array( self::class, 'maybe_migrate' ) );
	}

	public static function maybe_migrate(): void {
		if ( self::VERSION === get_option( self::OPTION ) ) {
			return;
		}

		self::migrate();
		update_option( self::OPTION, self::VERSION );
	}

	private static function migrate(): void {
		$forms = get_posts(
			array(
				'post_type'      => Post_Types::FORM,
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'ids',
				'meta_key'       => '_fforms_public',
			)
		);

		foreach ( $forms as $form_id ) {
			$mode   = get_post_meta( $form_id, '_fforms_mode', true );
			$mode   = 'headless' === $mode ? 'headless' : 'block';
			$public = (bool) get_post_meta( $form_id, '_fforms_public', true );

			if ( 'block' === $mode && $public ) {
				update_post_meta( $form_id, '_fforms_mode', 'public' );
			}

			delete_post_meta( $form_id, '_fforms_public' );
		}
	}
}
