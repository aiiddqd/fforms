<?php
/**
 * Deterministic code-form fixture for the local wp-env test environment.
 *
 * Only loaded when FFORMS_TEST_FIXTURES is truthy (see .wp-env.json), so it
 * never ships behavior into a real site.
 *
 * @package FForms
 */

namespace FForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'fforms_register_forms', function () {
	fforms_add_api_route(
		'e2e_contact',
		array(
			'title'           => 'E2E Contact',
			'fields'          => array(
				array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ),
				array( 'name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true ),
			),
			'origins'         => array( 'https://e2e.example.com' ),
			'success_message' => 'E2E thanks!',
			'notifications'   => array( 'enabled' => true, 'to' => 'e2e@example.com' ),
		)
	);
} );

// A generous limit keeps repeated local/CI test runs from tripping fforms' own rate limiter.
add_filter( 'fforms_rate_limit', function ( $limit, $rate_ref ) {
	return 'code:e2e_contact' === $rate_ref ? 1000 : $limit;
}, 10, 2 );
