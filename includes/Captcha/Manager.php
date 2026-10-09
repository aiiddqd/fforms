<?php
/**
 * Captcha provider registry, settings access, and the default hook handlers.
 *
 * The three built-in providers sit behind the same provider-agnostic hooks
 * as third-party add-ons: when no provider is configured the plugin behaves
 * exactly as before, and a later third-party filter can still override the
 * default decision.
 *
 * @package FForms
 */

namespace FForms\Captcha;

use FForms\Form_Ref;
use FForms\Settings;
use WP_Error;
use WP_REST_Request;

final class Manager {
	/** @var array<string, string> Frontend script handle per built-in provider id. */
	private const PROVIDER_HANDLES = array(
		'recaptcha'    => 'fforms-captcha-recaptcha',
		'turnstile'    => 'fforms-captcha-turnstile',
		'smartcaptcha' => 'fforms-captcha-smartcaptcha',
	);

	private const VERIFY_TIMEOUT = 5;

	/** The inline adapter config is printed once even for several forms per page. */
	private static bool $config_printed = false;

	public static function boot(): void {
		add_action( 'init', array( self::class, 'init' ), 12 );
	}

	/**
	 * Default handlers attach only behind a fully configured provider. A
	 * third-party filter registered later keeps overriding them, per the
	 * provider-agnostic contract.
	 */
	public static function init(): void {
		if ( ! self::is_ready() ) {
			return;
		}
		add_filter( 'fforms_captcha_required', array( self::class, 'required' ), 10, 3 );
		add_filter( 'fforms_verify_captcha', array( self::class, 'verify' ), 10, 4 );
		add_filter( 'fforms_captcha_markup', array( self::class, 'markup' ), 10, 2 );
	}

	/**
	 * All registered providers: the built-in three plus whatever add-ons
	 * appended through fforms_captcha_providers (id => class name or instance).
	 *
	 * @return array<string, Provider>
	 */
	public static function providers(): array {
		$registry = apply_filters(
			'fforms_captcha_providers',
			array(
				'recaptcha'    => Google_Recaptcha::class,
				'turnstile'    => Cloudflare_Turnstile::class,
				'smartcaptcha' => Yandex_Smartcaptcha::class,
			)
		);

		$providers = array();
		foreach ( (array) $registry as $id => $provider ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || 'none' === $id ) {
				continue;
			}
			if ( is_string( $provider ) && class_exists( $provider ) ) {
				$provider = new $provider();
			}
			if ( $provider instanceof Provider ) {
				$providers[ $id ] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * The captcha subarray of the shared FForms option, with defaults.
	 *
	 * @return array{provider: string, client_key: string, server_key: string, apply_main: bool, apply_forms: bool}
	 */
	public static function settings(): array {
		$all = Settings::get();

		return wp_parse_args(
			(array) ( $all['captcha'] ?? array() ),
			array(
				'provider'    => 'none',
				'client_key'  => '',
				'server_key'  => '',
				'apply_main'  => false,
				'apply_forms' => true,
			)
		);
	}

	public static function current_provider(): ?Provider {
		$settings  = self::settings();
		$providers = self::providers();

		return $providers[ $settings['provider'] ] ?? null;
	}

	/**
	 * The secret for token verification: the FFORMS_CAPTCHA_SERVER_KEY
	 * constant wins over the value saved in the option.
	 */
	public static function server_key(): string {
		if ( defined( 'FFORMS_CAPTCHA_SERVER_KEY' ) && '' !== (string) constant( 'FFORMS_CAPTCHA_SERVER_KEY' ) ) {
			return (string) constant( 'FFORMS_CAPTCHA_SERVER_KEY' );
		}

		return (string) self::settings()['server_key'];
	}

	/** A provider is chosen and both keys are in place. */
	public static function is_ready(): bool {
		$settings = self::settings();
		if ( 'none' === $settings['provider'] || '' === trim( (string) $settings['client_key'] ) ) {
			return false;
		}

		return '' !== trim( self::server_key() ) && null !== self::current_provider();
	}

	/** fforms_captcha_required: protect the routes the settings opted into. */
	public static function required( bool $required, Form_Ref $form, string $route ): bool {
		if ( $required ) {
			return true;
		}
		$settings = self::settings();
		if ( 'submit' === $route ) {
			return ! empty( $settings['apply_forms'] );
		}
		if ( 'main' === $route ) {
			return ! empty( $settings['apply_main'] );
		}

		return false;
	}

	/**
	 * fforms_verify_captcha: ask the provider endpoint and map every failure
	 * mode to a distinct error code. A provider rejection of the token is
	 * always a rejection; network, HTTP, and parse failures are
	 * captcha_unavailable and honour the fforms_captcha_fail_open filter.
	 */
	public static function verify( true|WP_Error $result, string $token, Form_Ref $form, WP_REST_Request $request ): true|WP_Error {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$provider = self::current_provider();
		if ( null === $provider ) {
			return $result;
		}

		$ip       = self::client_ip();
		$response = wp_remote_post(
			$provider->verify_endpoint(),
			array(
				'timeout' => self::VERIFY_TIMEOUT,
				'body'    => $provider->verify_params( $token, $ip ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::unavailable();
		}
		if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return self::unavailable();
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return self::unavailable();
		}
		if ( $provider->is_verified( $body ) ) {
			return true;
		}

		return new WP_Error(
			$provider->id() . '_rejected',
			__( 'The captcha token was rejected.', 'fforms' )
		);
	}

	/**
	 * fforms_captcha_markup: render the widget container and queue the
	 * provider script plus the FForms adapter exactly once per request —
	 * assets load only when a form actually rendered the slot.
	 */
	public static function markup( string $html, Form_Ref $form ): string {
		if ( '' !== $html ) {
			return $html;
		}
		$provider = self::current_provider();
		if ( null === $provider ) {
			return $html;
		}

		$client_key = (string) self::settings()['client_key'];
		$handle     = self::PROVIDER_HANDLES[ $provider->id() ] ?? 'fforms-captcha-' . $provider->id();
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, $provider->script_url(), array(), FFORMS_VERSION, true );
		}
		wp_enqueue_script( $handle );
		if ( ! wp_script_is( 'fforms-captcha', 'registered' ) ) {
			wp_register_script( 'fforms-captcha', FFORMS_URL . 'assets/captcha-provider.js', array( $handle ), FFORMS_VERSION, true );
		}
		wp_enqueue_script( 'fforms-captcha' );
		if ( ! self::$config_printed ) {
			wp_add_inline_script( 'fforms-captcha', 'window.fformsCaptchaConfig = ' . wp_json_encode( array( 'provider' => $provider->id(), 'sitekey' => $client_key ) ) . ';', 'before' );
			self::$config_printed = true;
		}

		$attrs = array_merge(
			array(
				'id'            => wp_unique_id( 'fforms-captcha-widget-' ),
				'class'         => 'fforms-captcha-widget',
				'data-provider' => $provider->id(),
				'data-sitekey'  => $client_key,
			),
			$provider->widget_container_attrs()
		);
		$rendered = '';
		foreach ( $attrs as $name => $value ) {
			$rendered .= sprintf( ' %s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}

		return '<div' . $rendered . '></div>';
	}

	/**
	 * Network-level failures are captcha_unavailable; the fail_open filter
	 * relaxes exactly this code, never a provider rejection.
	 */
	private static function unavailable(): true|WP_Error {
		if ( (bool) apply_filters( 'fforms_captcha_fail_open', false ) ) {
			return true;
		}

		return new WP_Error(
			'captcha_unavailable',
			__( 'The captcha service is unavailable. Try again later.', 'fforms' )
		);
	}

	/**
	 * The client IP as the REST pipeline computes it: REMOTE_ADDR only,
	 * proxy headers are not trusted, and fforms_client_ip may override.
	 */
	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return (string) apply_filters( 'fforms_client_ip', filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '' );
	}
}
