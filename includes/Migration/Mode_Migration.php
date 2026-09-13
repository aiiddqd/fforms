<?php
/**
 * One-time migration retiring the per-form `_fforms_mode` setting.
 *
 * Revision 2 folds the three legacy modes into the two entry points of the
 * plugin: `public` becomes the share link with a secret token, `headless`
 * becomes an ordinary builder form, and `block` was already that.
 *
 * @package FForms
 */

namespace FForms\Migration;

use FForms\Post_Types;
use FForms\Public_Form;

final class Mode_Migration {
	private const VERSION = '2';
	private const OPTION  = 'fforms_mode_migration_version';

	private const MODE_META        = '_fforms_mode';
	private const LEGACY_PUBLIC    = '_fforms_public';
	private const HEADLESS_BLOCK   = 'fforms/headless-schema';

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
				'post_type'   => Post_Types::FORM,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $forms as $form_id ) {
			$form_id = (int) $form_id;
			$mode    = (string) get_post_meta( $form_id, self::MODE_META, true );
			// A site that never ran revision 1 still carries the original toggle.
			$shared  = 'public' === $mode || (bool) get_post_meta( $form_id, self::LEGACY_PUBLIC, true );

			if ( 'headless' === $mode ) {
				self::convert_headless_form( $form_id );
			}

			update_post_meta( $form_id, Public_Form::ENABLED_META, $shared );
			if ( $shared ) {
				// The link changes address: the old /forms/{id}/ URL is gone either way,
				// and the new one is shown in the form's Publication panel.
				Public_Form::ensure_token( $form_id );
			}

			delete_post_meta( $form_id, self::MODE_META );
			delete_post_meta( $form_id, self::LEGACY_PUBLIC );
		}
	}

	/**
	 * The schema container block is gone, so its fields move into a regular
	 * `fforms/form` and gain the submit button a rendered form needs.
	 */
	private static function convert_headless_form( int $form_id ): void {
		$post = get_post( $form_id );
		if ( ! $post ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );
		$index  = null;
		foreach ( $blocks as $position => $block ) {
			if ( self::HEADLESS_BLOCK === ( $block['blockName'] ?? '' ) ) {
				$index = $position;
				break;
			}
		}
		if ( null === $index ) {
			return;
		}

		$inner   = $blocks[ $index ]['innerBlocks'] ?? array();
		$inner[] = array(
			'blockName'    => 'fforms/submit',
			'attrs'        => array( 'label' => __( 'Send', 'fforms' ) ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$blocks[ $index ] = array(
			'blockName'    => 'fforms/form',
			'attrs'        => array(),
			'innerBlocks'  => $inner,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $inner ), null ),
		);

		wp_update_post(
			array(
				'ID'           => $form_id,
				'post_content' => serialize_blocks( $blocks ),
			)
		);
	}
}
