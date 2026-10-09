<?php
/** FFORMS_CAPTCHA_SERVER_KEY constant priority check.
 * The constant cannot be undefined once set, so this case lives in its own
 * eval-file run which defines it before touching the manager.
 * Run with: ../../../../../bin/wp @local eval-file tests/captcha-constant.php
 */

use FForms\Captcha\Manager;
use FForms\REST_Controller;

if ( ! defined( 'FFORMS_CAPTCHA_SERVER_KEY' ) ) {
	define( 'FFORMS_CAPTCHA_SERVER_KEY', 'constant-server-key' );
}

function fforms_captcha_constant_assert( bool $condition, string $message ): void {
	if ( ! $condition ) throw new RuntimeException( $message );
}

$created         = array();
$http_requests   = array();
$original_option = get_option( 'fforms_smtp', array() );

$http_stub = static function ( $preempt, $args, $url ) use ( &$http_requests ) {
	if ( false !== $preempt || ! preg_match( '#^https://#', (string) $url ) ) {
		return $preempt;
	}
	$http_requests[] = array( 'url' => (string) $url, 'body' => $args['body'] );

	return array(
		'headers'  => array(),
		'body'     => '{"success":true}',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};

try {
	add_filter( 'pre_http_request', $http_stub, 10, 3 );
	add_filter( 'fforms_rate_limit', static fn() => 1000000 );

	// Provider selected, client key in the option, server key only via constant.
	$option            = (array) $original_option;
	$option['captcha'] = array(
		'provider'    => 'turnstile',
		'client_key'  => 'constant-test-client-key',
		'server_key'  => '',
		'apply_main'  => false,
		'apply_forms' => true,
	);
	update_option( 'fforms_smtp', $option );

	remove_all_filters( 'fforms_captcha_required' );
	remove_all_filters( 'fforms_verify_captcha' );
	remove_all_filters( 'fforms_captcha_markup' );
	Manager::init();

	fforms_captcha_constant_assert( 'constant-server-key' === Manager::server_key(), 'The constant must provide the server key.' );
	fforms_captcha_constant_assert( Manager::is_ready(), 'A constant-only server key must make the provider ready.' );

	$form_id = wp_insert_post( array( 'post_type' => 'fform', 'post_status' => 'publish', 'post_title' => 'Captcha constant test' ), true );
	if ( is_wp_error( $form_id ) ) throw new RuntimeException( $form_id->get_error_message() );
	$created[] = (int) $form_id;
	update_post_meta( (int) $form_id, '_fforms_schema', wp_json_encode( array( 'fields' => array(
		array( 'name' => 'email', 'type' => 'email', 'required' => true ),
	) ) ) );

	$request = new WP_REST_Request( 'POST', '/fforms/v1/submit' );
	$request->set_body_params( array(
		'form_id'       => (int) $form_id,
		'fields'        => array( 'email' => 'a@example.test' ),
		'captcha_token' => 'token-under-constant',
	) );
	$response = REST_Controller::submit( $request );
	fforms_captcha_constant_assert( $response instanceof WP_REST_Response && 201 === $response->get_status(), 'A verified token under a constant key must store the entry.' );
	$created[] = (int) $response->get_data()['entry_id'];
	fforms_captcha_constant_assert( 1 === count( $http_requests ), 'Exactly one verification request must fire.' );
	fforms_captcha_constant_assert( 'constant-server-key' === ( $http_requests[0]['body']['secret'] ?? '' ), 'The verification must use the constant server key, not the empty option.' );

	echo "FForms captcha constant check passed.\n";
} finally {
	remove_all_filters( 'pre_http_request' );
	remove_all_filters( 'fforms_rate_limit' );
	remove_all_filters( 'fforms_captcha_required' );
	remove_all_filters( 'fforms_verify_captcha' );
	remove_all_filters( 'fforms_captcha_markup' );
	update_option( 'fforms_smtp', $original_option );
	foreach ( $created as $post_id ) wp_delete_post( $post_id, true );
}
