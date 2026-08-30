<?php
/**
 * Public procedural API for registering code-based (headless) forms.
 *
 * @package FForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'fforms_add_api_route' ) ) {
	/**
	 * Register a form in code so it becomes reachable through fforms/v1 by a string key.
	 *
	 * Must be called on the `fforms_register_forms` action (fires on `init`, priority 5).
	 *
	 * @param string               $key  Unique key, `^[a-z0-9_]{1,32}$`.
	 * @param array<string, mixed> $args Form definition: title, fields, origins, success_message, notifications, autoreply.
	 */
	function fforms_add_api_route( string $key, array $args ): true|WP_Error {
		return \FForms\Registry\Code_Forms::register( $key, $args );
	}
}
