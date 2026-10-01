<?php
/** Provider-agnostic captcha pipeline checks.
 * Run with: npx wp-env run cli wp eval-file wp-content/plugins/_fforms/tests/captcha.php
 */

use FForms\REST_Controller;

function fforms_captcha_assert( bool $condition, string $message ): void {
	if ( ! $condition ) throw new RuntimeException( $message );
}

$created = array();
$required = static fn( $value, $form, $route ) => true;
$verify_calls = 0;
$verify_fail = static function ( $result, $token ) use ( &$verify_calls ) {
	++$verify_calls;
	return new WP_Error( 'provider_rejected', 'private provider detail' );
};

try {
	$form_id = wp_insert_post( array( 'post_type' => 'fform', 'post_status' => 'publish', 'post_title' => 'Captcha test' ), true );
	if ( is_wp_error( $form_id ) ) throw new RuntimeException( $form_id->get_error_message() );
	$created[] = (int) $form_id;
	update_post_meta( (int) $form_id, '_fforms_schema', wp_json_encode( array( 'fields' => array(
		array( 'name' => 'name', 'type' => 'text', 'required' => true ),
		array( 'name' => 'email', 'type' => 'email', 'required' => true ),
		array( 'name' => 'message', 'type' => 'textarea', 'required' => true ),
	) ) ) );
	add_filter( 'fforms_captcha_required', $required, 10, 3 );
	add_filter( 'fforms_verify_captcha', $verify_fail, 10, 2 );

	$missing = new WP_REST_Request( 'POST', '/fforms/v1/submit' );
	$missing->set_body_params( array( 'form_id' => $form_id, 'fields' => array( 'name' => 'A', 'email' => 'a@example.test', 'message' => 'Hi' ) ) );
	$response = REST_Controller::submit( $missing );
	fforms_captcha_assert( is_wp_error( $response ) && 'fforms_captcha_required' === $response->get_error_code(), 'A required empty token must return fforms_captcha_required.' );
	fforms_captcha_assert( 422 === $response->get_error_data()['status'], 'A missing required token must use HTTP 422.' );

	$failed = new WP_REST_Request( 'POST', '/fforms/v1/submit' );
	$failed->set_body_params( array( 'form_id' => $form_id, 'fields' => array( 'name' => 'A', 'email' => 'a@example.test', 'message' => 'Hi' ), 'captcha_token' => 'invalid' ) );
	$response = REST_Controller::submit( $failed );
	fforms_captcha_assert( is_wp_error( $response ) && 'fforms_captcha_failed' === $response->get_error_code(), 'A provider rejection must return fforms_captcha_failed.' );
	fforms_captcha_assert( 'provider_rejected' === $response->get_error_data()['reason'], 'The provider error code must be exposed as data.reason.' );
	fforms_captcha_assert( 1 === $verify_calls, 'The valid form must reach provider verification once.' );

	$invalid_fields = new WP_REST_Request( 'POST', '/fforms/v1/submit' );
	$invalid_fields->set_body_params( array( 'form_id' => $form_id, 'fields' => array( 'name' => '', 'email' => 'bad', 'message' => '' ), 'captcha_token' => 'unused' ) );
	$response = REST_Controller::submit( $invalid_fields );
	fforms_captcha_assert( is_wp_error( $response ) && 'fforms_validation_failed' === $response->get_error_code(), 'Invalid fields must be rejected before captcha verification.' );
	fforms_captcha_assert( 1 === $verify_calls, 'Field validation errors must not consume the captcha token.' );

	$honeypot = new WP_REST_Request( 'POST', '/fforms/v1/submit' );
	$honeypot->set_body_params( array( 'form_id' => $form_id, 'fields' => array(), 'website' => 'bot' ) );
	$response = REST_Controller::submit( $honeypot );
	fforms_captcha_assert( $response instanceof WP_REST_Response && 200 === $response->get_status(), 'The honeypot must retain its fake 200 response.' );
	fforms_captcha_assert( 1 === $verify_calls, 'The honeypot must bypass captcha verification.' );

	$main = new WP_REST_Request( 'POST', '/fforms/v1/main' );
	$main->set_body_params( array( 'name' => 'A', 'captcha_token' => 'captured-token', 'smart-token' => 'browser-input' ) );
	add_filter( 'fforms_captcha_required', static fn( $value, $form, $route ) => 'main' === $route, 20, 3 );
	add_filter( 'fforms_verify_captcha', static fn() => true, 20 );
	$response = REST_Controller::submit_main( $main );
	fforms_captcha_assert( $response instanceof WP_REST_Response && 201 === $response->get_status(), '/main must accept a verified captcha token.' );
	$entry = get_post_meta( $response->get_data()['entry_id'], '_fforms_data', true );
	$created[] = (int) $response->get_data()['entry_id'];
	$data = json_decode( (string) $entry, true );
	fforms_captcha_assert( ! array_key_exists( 'captcha_token', $data ) && ! array_key_exists( 'smart-token', $data ), 'Reserved captcha values must not be stored as submitted fields.' );
	remove_all_filters( 'fforms_captcha_required' );
	remove_all_filters( 'fforms_verify_captcha' );
	echo "FForms captcha pipeline checks passed.\n";
} finally {
	remove_all_filters( 'fforms_captcha_required' );
	remove_all_filters( 'fforms_verify_captcha' );
	foreach ( $created as $post_id ) wp_delete_post( $post_id, true );
}
