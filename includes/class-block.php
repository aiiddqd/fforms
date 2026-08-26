<?php
/**
 * Dynamic Gutenberg form block.
 *
 * @package FForms
 */

namespace FForms;

final class Block {
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ), 20 );
	}

	public static function register(): void {
		$build    = FFORMS_DIR . 'build';
		$manifest = $build . '/blocks-manifest.php';
		if ( ! file_exists( $manifest ) ) {
			return;
		}
		if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
			wp_register_block_types_from_metadata_collection( $build, $manifest );
			return;
		}
		$metadata = require $manifest;
		if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
			wp_register_block_metadata_collection( $build, $manifest );
		}
		foreach ( array_keys( $metadata ) as $path ) {
			register_block_type( $build . '/' . $path );
		}
	}
}
