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
		add_action( 'init', array( Form_Types::class, 'register' ), 11 );

		Post_Types::boot();
		Form_Types::boot();
		Registry\Code_Forms::boot();
		Migration\Legacy_Migration::boot();
		Migration\Mode_Migration::boot();
		Migration\Type_Meta_Migration::boot();
		Dashboard::boot();
		Settings::boot();
		REST_Controller::boot();
		CORS::boot();
		Public_Form::boot();
		Block::boot();
		Shortcode::boot();
		Export::boot();
	}

	public static function activate(): void {
		Post_Types::register();
		Form_Types::register();
		Default_Forms::seed();
		Public_Form::register_rewrite_rule();
		flush_rewrite_rules();
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain( 'fforms', false, dirname( plugin_basename( FFORMS_FILE ) ) . '/languages' );
	}
}
