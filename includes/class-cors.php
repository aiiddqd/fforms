<?php
/**
 * Restrictive, per-form CORS for the fforms/v1 namespace.
 *
 * WordPress core's default `rest_send_cors_headers()` reflects any Origin and
 * always sends `Access-Control-Allow-Credentials: true`. For fforms/v1 we
 * replace that with an exact-match per-form allowlist and no credentials.
 *
 * @package FForms
 */

namespace FForms;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class CORS {
	private const ROUTE_PREFIX = '/fforms/v1';

	public static function boot(): void {
		add_filter( 'rest_pre_dispatch', array( self::class, 'short_circuit_preflight' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( self::class, 'send_headers' ), 20, 3 );
	}

	public static function short_circuit_preflight( mixed $result, WP_REST_Server $server, WP_REST_Request $request ): mixed {
		if ( 'OPTIONS' !== $request->get_method() || ! self::is_own_route( $request ) ) {
			return $result;
		}
		return new WP_REST_Response( null, 204 );
	}

	public static function send_headers( mixed $served, mixed $result, WP_REST_Request $request ): mixed {
		if ( ! self::is_own_route( $request ) ) {
			return $served;
		}

		header_remove( 'Access-Control-Allow-Credentials' );

		$is_preflight = 'OPTIONS' === $request->get_method();
		$origin       = self::request_origin();
		$allowed      = $is_preflight ? self::global_allowed_origins() : self::allowed_origins_for( self::resolve_form( $request ) );

		if ( '' !== $origin && in_array( $origin, $allowed, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Vary: Origin', false );
		} else {
			header_remove( 'Access-Control-Allow-Origin' );
		}

		if ( $is_preflight ) {
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
			header( 'Access-Control-Max-Age: 600' );
		}

		return $served;
	}

	private static function is_own_route( WP_REST_Request $request ): bool {
		return str_starts_with( $request->get_route(), self::ROUTE_PREFIX );
	}

	private static function request_origin(): string {
		$origin = get_http_origin();
		return $origin && 'null' !== $origin ? sanitize_url( $origin ) : '';
	}

	/**
	 * A real (non-preflight) request always carries form_id/form_key, either as
	 * a submit() body param or as a /forms/{id|key} route segment.
	 */
	private static function resolve_form( WP_REST_Request $request ): ?Form_Ref {
		$form_id  = absint( $request->get_param( 'form_id' ) );
		$form_key = sanitize_key( (string) $request->get_param( 'form_key' ) );

		if ( ! $form_id && '' === $form_key ) {
			$matches = array();
			if ( preg_match( '#^/fforms/v1/forms/(\d+)#', $request->get_route(), $matches ) ) {
				$form_id = absint( $matches[1] );
			} elseif ( preg_match( '#^/fforms/v1/forms/([a-z0-9_]+)#', $request->get_route(), $matches ) ) {
				$form_key = sanitize_key( $matches[1] );
			}
		}
		if ( ! $form_id && '' === $form_key ) {
			return null;
		}

		$ref = Form_Locator::resolve( $form_id ?: $form_key );
		return is_wp_error( $ref ) ? null : $ref;
	}

	/** @return array<int, string> */
	private static function allowed_origins_for( ?Form_Ref $form ): array {
		$origins = (array) apply_filters( 'fforms_allowed_origins', $form ? $form->origins : array(), $form );
		return array_values( array_unique( array_map( 'untrailingslashit', $origins ) ) );
	}

	/**
	 * Preflight OPTIONS carries no body, so the target form isn't known yet;
	 * accept any origin allowed by *some* registered code form.
	 *
	 * @return array<int, string>
	 */
	private static function global_allowed_origins(): array {
		$origins = array();
		foreach ( Registry\Code_Forms::all() as $form_ref ) {
			$origins = array_merge( $origins, $form_ref->origins );
		}
		$origins = array_merge( $origins, self::allowed_origins_for( null ) );
		return array_values( array_unique( array_map( 'untrailingslashit', $origins ) ) );
	}
}
