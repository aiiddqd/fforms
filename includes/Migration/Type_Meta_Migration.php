<?php
/**
 * One-time cleanup retiring the per-form `_fforms_type` setting.
 *
 * Form types are the `fform_type` taxonomy now: a form is its own type through
 * the term created on publish, so the old `contact`/`lead` value classifies
 * nothing. Unregistering the meta already made it inert; dropping the rows keeps
 * them out of meta queries, custom-field lists, and site exports.
 *
 * @package FForms
 */

namespace FForms\Migration;

final class Type_Meta_Migration {
	private const VERSION = '1';
	private const OPTION  = 'fforms_type_meta_migration_version';

	private const TYPE_META = '_fforms_type';

	public static function boot(): void {
		add_action( 'admin_init', array( self::class, 'maybe_migrate' ) );
	}

	public static function maybe_migrate(): void {
		if ( self::VERSION === get_option( self::OPTION ) ) {
			return;
		}

		// The key belongs to this plugin alone and only ever held `contact` or
		// `lead`, so there is nothing to preserve and no post type to narrow to.
		delete_post_meta_by_key( self::TYPE_META );
		update_option( self::OPTION, self::VERSION );
	}
}
