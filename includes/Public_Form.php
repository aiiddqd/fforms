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
	private const EMBED_VAR       = 'fforms_embed';
	private const REWRITE_VERSION = '2';
	private const REWRITE_OPTION  = 'fforms_public_form_rewrite_version';

	/** Post meta: the share link is on, and the secret that addresses the form. */
	public const ENABLED_META = '_fforms_share_link';
	public const TOKEN_META   = '_fforms_share_token';

	private const TOKEN_PATTERN = '/^[a-f0-9]{16}$/';

	public static function boot(): void {
		add_action( 'init', array( self::class, 'register_rewrite_rule' ) );
		add_action( 'init', array( self::class, 'maybe_flush_rewrite_rules' ), 99 );
		add_filter( 'query_vars', array( self::class, 'add_query_var' ) );
		add_action( 'wp_after_insert_post', array( self::class, 'issue_token_on_publish' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'render' ) );
	}

	public static function register_rewrite_rule(): void {
		add_rewrite_rule( '^forms/([a-f0-9]{16})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/** @param array<int, string> $vars */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::EMBED_VAR;
		return $vars;
	}

	public static function maybe_flush_rewrite_rules(): void {
		if ( self::REWRITE_VERSION === get_option( self::REWRITE_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION );
	}

	public static function sanitize_token( mixed $value ): string {
		$value = strtolower( trim( (string) $value ) );
		return preg_match( self::TOKEN_PATTERN, $value ) ? $value : '';
	}

	public static function token( int $form_id ): string {
		return self::sanitize_token( get_post_meta( $form_id, self::TOKEN_META, true ) );
	}

	/**
	 * A form carries its token from its first publish, whether or not the share
	 * link is on: turning the toggle on should never mean waiting for a new URL.
	 */
	public static function issue_token_on_publish( int $post_id, \WP_Post $post ): void {
		if ( Post_Types::FORM !== $post->post_type || 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::ensure_token( $post_id );
	}

	public static function ensure_token( int $form_id ): string {
		$token = self::token( $form_id );
		if ( '' !== $token ) {
			return $token;
		}

		return self::regenerate_token( $form_id );
	}

	/** Issuing a new token invalidates the previous link immediately. */
	public static function regenerate_token( int $form_id ): string {
		$token = bin2hex( random_bytes( 8 ) );
		update_post_meta( $form_id, self::TOKEN_META, $token );
		return $token;
	}

	/**
	 * The token is the only address a form has, so it must resolve without a
	 * public query: meta lookup, restricted to published forms.
	 */
	public static function form_id_by_token( string $token ): int {
		$token = self::sanitize_token( $token );
		if ( '' === $token ) {
			return 0;
		}

		$forms = get_posts(
			array(
				'post_type'        => Post_Types::FORM,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => self::TOKEN_META,
				'meta_value'       => $token,
			)
		);

		return $forms ? (int) $forms[0] : 0;
	}

	public static function url( int $form_id ): string {
		$token = self::token( $form_id );
		return '' === $token ? '' : home_url( user_trailingslashit( 'forms/' . $token ) );
	}

	/**
	 * Same form, stripped of the theme's header, footer and navigation.
	 * The shareable URL keeps the full page; only frames use this one.
	 */
	public static function embed_url( int $form_id ): string {
		$url = self::url( $form_id );
		return '' === $url ? '' : add_query_arg( self::EMBED_VAR, '1', $url );
	}

	private static function is_embed_request(): bool {
		return '' !== (string) get_query_var( self::EMBED_VAR );
	}

	/** Whether the form is reachable by its link, iframe and js-snippet. */
	public static function is_enabled( int $form_id ): bool {
		return (bool) get_post_meta( $form_id, self::ENABLED_META, true );
	}

	/** URL of the script third-party sites load to embed a form. */
	public static function embed_script_url(): string {
		return FFORMS_URL . 'assets/embed.js';
	}

	/** Ready-to-copy iframe snippet; empty while the form has no link. */
	public static function iframe_snippet( int $form_id, string $title = '' ): string {
		$embed_url = self::embed_url( $form_id );
		if ( '' === $embed_url ) {
			return '';
		}

		return sprintf(
			'<iframe src="%s" title="%s" style="width:100%%;border:0" height="600" loading="lazy"></iframe>',
			esc_url( $embed_url ),
			esc_attr( $title ?: get_the_title( $form_id ) )
		);
	}

	/** Ready-to-copy script snippet; the script injects and auto-sizes the iframe. */
	public static function script_snippet( int $form_id ): string {
		$embed_url = self::embed_url( $form_id );
		if ( '' === $embed_url ) {
			return '';
		}

		return sprintf(
			'<script src="%s" data-fforms-form="%d" data-fforms-origin="%s" data-fforms-src="%s"></script>',
			esc_url( self::embed_script_url() ),
			absint( $form_id ),
			esc_url( home_url() ),
			esc_url( $embed_url )
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
		$token = self::sanitize_token( get_query_var( self::QUERY_VAR ) );
		if ( '' === $token ) {
			return;
		}

		$form_id = self::form_id_by_token( $token );
		$form    = $form_id ? get_post( $form_id ) : null;
		if ( ! $form || Post_Types::FORM !== $form->post_type || 'publish' !== $form->post_status || ! self::is_enabled( $form_id ) ) {
			self::render_not_found();
		}

		self::block_indexing();
		Block::enqueue_form_assets();
		self::enqueue_frame_reporter( $form_id );
		$form_markup = Form_Renderer::render_form( $form_id );

		add_filter( 'pre_get_document_title', static fn(): string => get_the_title( $form_id ) );

		if ( self::is_embed_request() ) {
			self::render_embed( $form_id, $form_markup );
		}

		add_filter( 'body_class', static fn( array $classes ): array => array_merge( $classes, array( 'fforms-public-form' ) ) );
		status_header( 200 );
		get_header();
		echo '<main id="primary" class="site-main fforms-public-form__content"><article class="fforms-public-form__article"><header class="fforms-public-form__header"><h1 class="fforms-public-form__title">' . esc_html( get_the_title( $form_id ) ) . '</h1></header>' . $form_markup . '</article></main>';
		get_footer();
		exit;
	}

	/**
	 * Minimal standalone document for iframe embedding: no theme header, footer or
	 * admin bar, but wp_head/wp_footer still run so the form keeps its block and
	 * Global Styles CSS and the Interactivity runtime.
	 */
	private static function render_embed( int $form_id, string $form_markup ): void {
		self::block_indexing();
		show_admin_bar( false );
		add_filter( 'body_class', static fn( array $classes ): array => array_merge( $classes, array( 'fforms-public-form', 'fforms-embed' ) ) );
		status_header( 200 );
		nocache_headers();
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
<style>
/* Themes routinely give html/body a 100% height and their own overflow. Inside a
   frame that makes the document report the frame's height instead of its own,
   so the form can never be measured and ends up clipped. */
html,body{height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important;margin:0;padding:0;background:transparent}
body.fforms-embed{display:block!important}
.fforms-embed__content{height:auto!important;min-height:0!important;overflow:visible!important;padding:0;margin:0}
</style>
</head>
<body <?php body_class(); ?>>
<main class="fforms-embed__content"><?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-rendered block markup ?></main>
<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * A form link is shared with the people meant to fill it in, never indexed:
	 * the meta tag covers crawlers reading the page, the header covers the rest.
	 */
	private static function block_indexing(): void {
		// Not wp_robots_no_robots(): on a public site it emits "noindex, follow",
		// and a shared form link must not be crawled onwards either.
		add_filter(
			'wp_robots',
			static function ( array $robots ): array {
				unset( $robots['index'], $robots['follow'] );
				$robots['noindex']  = true;
				$robots['nofollow'] = true;
				return $robots;
			}
		);
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
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
