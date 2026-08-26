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
			self::register_development_fallback();
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

	/**
	 * Enqueue frontend assets when a form is rendered outside normal post content.
	 */
	public static function enqueue_form_assets(): void {
		$block = \WP_Block_Type_Registry::get_instance()->get_registered( 'fforms/form' );
		if ( ! $block ) {
			return;
		}

		foreach ( array( 'style_handles', 'view_style_handles' ) as $property ) {
			foreach ( $block->{$property} ?? array() as $handle ) {
				wp_enqueue_style( $handle );
			}
		}
		foreach ( array( 'script_handles', 'view_script_handles' ) as $property ) {
			foreach ( $block->{$property} ?? array() as $handle ) {
				wp_enqueue_script( $handle );
			}
		}
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			foreach ( $block->view_script_module_ids ?? array() as $id ) {
				wp_enqueue_script_module( $id );
			}
		}
	}

	/**
	 * Keep a fresh clone usable before wp-scripts has created build/.
	 * Production always uses the metadata collection above.
	 */
	private static function register_development_fallback(): void {
		wp_register_script( 'fforms-fallback-editor', FFORMS_URL . 'assets/fallback-editor.js', array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ), FFORMS_VERSION, true );
		wp_register_script( 'fforms-fallback-view', FFORMS_URL . 'assets/fallback-view.js', array(), FFORMS_VERSION, true );
		wp_register_style( 'fforms-fallback-style', FFORMS_URL . 'assets/fallback-view.css', array(), FFORMS_VERSION );

		register_block_type( 'fforms/form', array(
			'api_version' => 3,
			'editor_script' => 'fforms-fallback-editor',
			'style' => 'fforms-fallback-style',
			'view_script' => 'fforms-fallback-view',
			'attributes' => self::form_attributes(),
			'render_callback' => static fn( $attributes, $content, $block ): string => \FForms\Blocks\Form_Renderer::render( $attributes, $content, $block ),
		) );
		foreach ( array( 'text', 'textarea', 'email', 'tel', 'url', 'number', 'select', 'radio', 'checkbox', 'hidden' ) as $type ) {
			register_block_type( 'fforms/field-' . $type, array(
				'api_version' => 3,
				'editor_script' => 'fforms-fallback-editor',
				'attributes' => self::field_attributes(),
				'render_callback' => static fn( $attributes, $content, $block ): string => \FForms\Blocks\Form_Renderer::render_field( $attributes, $block->name ),
			) );
		}
		register_block_type( 'fforms/submit', array(
			'api_version' => 3,
			'editor_script' => 'fforms-fallback-editor',
			'attributes' => array( 'label' => array( 'type' => 'string', 'default' => '' ) ),
			'render_callback' => static fn( $attributes ): string => \FForms\Blocks\Form_Renderer::render_submit( $attributes ),
		) );
	}

	private static function form_attributes(): array {
		return array( 'ref' => array( 'type' => 'integer', 'default' => 0 ), 'formId' => array( 'type' => 'integer', 'default' => 0 ), 'showTitle' => array( 'type' => 'boolean', 'default' => false ), 'submitLabel' => array( 'type' => 'string', 'default' => '' ) );
	}

	private static function field_attributes(): array {
		return array( 'fieldId' => array( 'type' => 'string', 'default' => '' ), 'name' => array( 'type' => 'string', 'default' => '' ), 'label' => array( 'type' => 'string', 'default' => '' ), 'required' => array( 'type' => 'boolean', 'default' => false ), 'placeholder' => array( 'type' => 'string', 'default' => '' ), 'maxLength' => array( 'type' => 'number', 'default' => 0 ), 'options' => array( 'type' => 'array', 'default' => array() ) );
	}
}
