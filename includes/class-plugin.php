<?php
/**
 * Main plugin coordinator.
 *
 * @package FForms
 */

namespace FForms;

final class Plugin {
	public static function boot(): void {
		add_action( 'plugins_loaded', array( self::class, 'load_textdomain' ) );
		add_action( 'init', array( Post_Types::class, 'register' ) );

		Post_Types::boot();
		Migration\Legacy_Migration::boot();
		Settings::boot();
		REST_Controller::boot();
		Block::boot();
		Export::boot();
	}

	public static function activate(): void {
		Post_Types::register();
		flush_rewrite_rules();
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain( 'fforms', false, dirname( plugin_basename( FFORMS_FILE ) ) . '/languages' );
	}
}
