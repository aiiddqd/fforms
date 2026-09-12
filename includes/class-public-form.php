<?php
/**
 * Public, shareable form pages.
 *
 * @package FForms
 */

namespace FForms;

use FForms\Blocks\Form_Renderer;

final class Public_Form {
	private const QUERY_VAR       = 'fforms_public_form';
	private const REWRITE_VERSION = '1';
	private const REWRITE_OPTION  = 'fforms_public_form_rewrite_version';

	public static function boot(): void {
		add_action( 'init', array( self::class, 'register_rewrite_rule' ) );
		add_action( 'init', array( self::class, 'maybe_flush_rewrite_rules' ), 99 );
		add_filter( 'query_vars', array( self::class, 'add_query_var' ) );
		add_action( 'template_redirect', array( self::class, 'render' ) );
	}

	public static function register_rewrite_rule(): void {
		add_rewrite_rule( '^forms/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/** @param array<int, string> $vars */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function maybe_flush_rewrite_rules(): void {
		if ( self::REWRITE_VERSION === get_option( self::REWRITE_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION );
	}

	public static function url( int $form_id ): string {
		return home_url( user_trailingslashit( 'forms/' . absint( $form_id ) ) );
	}

	public static function is_enabled( int $form_id ): bool {
		return 'public' === Post_Types::form_mode( $form_id );
	}

	/** URL of the script third-party sites load to embed a form. */
	public static function embed_script_url(): string {
		return FFORMS_URL . 'assets/embed.js';
	}

	/** Ready-to-copy iframe snippet for a public form. */
	public static function iframe_snippet( int $form_id, string $title = '' ): string {
		return sprintf(
			'<iframe src="%s" title="%s" style="width:100%%;border:0" height="600" loading="lazy"></iframe>',
			esc_url( self::url( $form_id ) ),
			esc_attr( $title ?: get_the_title( $form_id ) )
		);
	}

	/** Ready-to-copy script snippet; the script injects and auto-sizes the iframe. */
	public static function script_snippet( int $form_id ): string {
		return sprintf(
			'<script src="%s" data-fforms-form="%d" data-fforms-origin="%s"></script>',
			esc_url( self::embed_script_url() ),
			absint( $form_id ),
			esc_url( home_url() )
		);
	}

	/**
	 * Let an embedding page size the frame to the form.
	 * Height is the only thing that ever leaves this page.
	 */
	private static function enqueue_frame_reporter( int $form_id ): void {
		$path    = FFORMS_DIR . 'assets/public-form-frame.js';
		$version = file_exists( $path ) ? (string) filemtime( $path ) : FFORMS_VERSION;
		wp_enqueue_script( 'fforms-public-form-frame', FFORMS_URL . 'assets/public-form-frame.js', array(), $version, true );
		wp_add_inline_script(
			'fforms-public-form-frame',
			'window.fformsPublicForm = ' . wp_json_encode( array( 'formId' => $form_id ) ) . ';',
			'before'
		);
	}

	public static function render(): void {
		$form_id = absint( get_query_var( self::QUERY_VAR ) );
		if ( ! $form_id ) {
			return;
		}

		$form = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type || 'publish' !== $form->post_status || ! self::is_enabled( $form_id ) ) {
			self::render_not_found();
		}

		Block::enqueue_form_assets();
		self::enqueue_frame_reporter( $form_id );
		$form_markup = Form_Renderer::render_form( $form_id );

		add_filter( 'pre_get_document_title', static fn(): string => get_the_title( $form_id ) );
		add_filter( 'body_class', static fn( array $classes ): array => array_merge( $classes, array( 'fforms-public-form' ) ) );
		status_header( 200 );
		get_header();
		echo '<main id="primary" class="site-main fforms-public-form__content"><article class="fforms-public-form__article"><header class="fforms-public-form__header"><h1 class="fforms-public-form__title">' . esc_html( get_the_title( $form_id ) ) . '</h1></header>' . $form_markup . '</article></main>';
		get_footer();
		exit;
	}

	private static function render_not_found(): void {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		$template = get_404_template();
		if ( $template ) {
			include $template;
		}
		exit;
	}
}
