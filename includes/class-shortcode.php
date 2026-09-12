<?php
/**
 * Shortcode insertion point: [fform id=123].
 *
 * @package FForms
 */

namespace FForms;

use FForms\Blocks\Form_Renderer;

final class Shortcode {
	public const TAG = 'fform';

	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_assets' ) );
	}

	public static function register(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/**
	 * Load form assets before wp_head for the common case of a shortcode in post content.
	 * Rendering from a widget or a theme template falls back to the late enqueue in render().
	 */
	public static function maybe_enqueue_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( $post->post_content, self::TAG ) ) {
			return;
		}

		Block::enqueue_form_assets();
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function render( $atts ): string {
		$atts    = shortcode_atts( array( 'id' => 0 ), is_array( $atts ) ? $atts : array(), self::TAG );
		$form_id = absint( $atts['id'] );

		Block::enqueue_form_assets();

		return Form_Renderer::render_form(
			$form_id,
			__( 'Форма не найдена: проверьте id в шорткоде [fform].', 'fforms' )
		);
	}
}
