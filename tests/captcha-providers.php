<?php
/** Native captcha provider checks: registry, settings, verification, fail-closed policy.
 * Run with: ../../../../../bin/wp @local eval-file tests/captcha-providers.php
 */

use FForms\Captcha\Manager;
use FForms\REST_Controller;

function fforms_captcha_providers_assert( bool $condition, string $message ): void {
	if ( ! $condition ) throw new RuntimeException( $message );
}

/** Fourth provider registered through the registry filter. */
class FForms_Test_Captcha_Provider implements \FForms\Captcha\Provider {
	public function id(): string { return 'testprovider'; }
	public function label(): string { return 'Test Provider'; }
	public function script_url(): string { return 'https://example.test/captcha.js?render=explicit'; }
	public function verify_endpoint(): string { return 'https://example.test/verify'; }
	public function widget_container_attrs(): array { return array(); }
	public function verify_params( string $token, string $ip ): array {
		return array( 'secret' => Manager::server_key(), 'response' => $token, 'remoteip' => $ip );
	}
	public function is_verified( array $response_body ): bool { return true === ( $response_body['success'] ?? false ); }
}

$created         = array();
$http_requests   = array();
$http_behavior   = 'success';
$original_option = get_option( 'fforms_smtp', array() );

$http_response = static fn( string $body, int $code = 200 ): array => array(
	'headers'  => array(),
	'body'     => $body,
	'response' => array( 'code' => $code, 'message' => 'OK' ),
	'cookies'  => array(),
	'filename' => null,
);

$http_stub = static function ( $preempt, $args, $url ) use ( &$http_requests, &$http_behavior, $http_response ) {
	if ( false !== $preempt || ! preg_match( '#^https://#', (string) $url ) ) {
		return $preempt;
	}
	$http_requests[] = array( 'url' => (string) $url, 'args' => $args );
	switch ( $http_behavior ) {
		case 'error':
			return new WP_Error( 'http_request_failed', 'Simulated network failure.' );
		case 'broken':
			return $http_response( 'this is not json' );
		case 'reject':
			return $http_response( '{"success":false,"error-codes":["invalid-input-response"]}' );
		default:
			return $http_response( '{"success":true}' );
	}
};

$configure = static function ( string $provider, array $overrides = array() ) use ( $original_option ): void {
	$captcha = array_merge(
		array(
			'provider'    => $provider,
			'client_key'  => 'test-client-key',
			'server_key'  => 'test-server-key',
			'apply_main'  => false,
			'apply_forms' => true,
		),
		$overrides
	);
	$option            = (array) $original_option;
	$option['captcha'] = $captcha;
	update_option( 'fforms_smtp', $option );
	remove_all_filters( 'fforms_captcha_required' );
	remove_all_filters( 'fforms_verify_captcha' );
	remove_all_filters( 'fforms_captcha_markup' );
	Manager::init();
};

$submit_form = static function ( int $form_id, array $fields, string $token = '' ) {
	$request = new WP_REST_Request( 'POST', '/fforms/v1/submit' );
	$params  = array( 'form_id' => $form_id, 'fields' => $fields );
	if ( '' !== $token ) {
		$params['captcha_token'] = $token;
	}
	$request->set_body_params( $params );

	return REST_Controller::submit( $request );
};

$submit_main = static function ( string $token = '' ) {
	$request = new WP_REST_Request( 'POST', '/fforms/v1/main' );
	$params  = array( 'email' => 'a@example.test' );
	if ( '' !== $token ) {
		$params['captcha_token'] = $token;
	}
	$request->set_body_params( $params );

	return REST_Controller::submit_main( $request );
};

try {
	add_filter( 'pre_http_request', $http_stub, 10, 3 );
	add_filter( 'fforms_rate_limit', static fn() => 1000000 );

	// Give the unit checks below a known server key in the option.
	$bootstrap_option            = (array) $original_option;
	$bootstrap_option['captcha'] = array(
		'provider'    => 'none',
		'client_key'  => '',
		'server_key'  => 'test-server-key',
		'apply_main'  => false,
		'apply_forms' => true,
	);
	update_option( 'fforms_smtp', $bootstrap_option );

	// Case 10 (unit): each provider maps its protocol correctly.
	$recaptcha = new \FForms\Captcha\Google_Recaptcha();
	fforms_captcha_providers_assert( 'recaptcha' === $recaptcha->id(), 'reCAPTCHA id must be recaptcha.' );
	fforms_captcha_providers_assert( 'https://www.google.com/recaptcha/api.js?render=explicit' === $recaptcha->script_url(), 'reCAPTCHA script URL mismatch.' );
	fforms_captcha_providers_assert( 'https://www.google.com/recaptcha/api/siteverify' === $recaptcha->verify_endpoint(), 'reCAPTCHA verify endpoint mismatch.' );
	$params = $recaptcha->verify_params( 'tok', '1.2.3.4' );
	fforms_captcha_providers_assert( array( 'secret' => 'test-server-key', 'response' => 'tok', 'remoteip' => '1.2.3.4' ) === $params, 'reCAPTCHA verify params must be secret/response/remoteip.' );
	fforms_captcha_providers_assert( $recaptcha->is_verified( array( 'success' => true ) ) && ! $recaptcha->is_verified( array( 'success' => false ) ), 'reCAPTCHA success must map from success === true.' );

	$turnstile = new \FForms\Captcha\Cloudflare_Turnstile();
	fforms_captcha_providers_assert( 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit' === $turnstile->script_url(), 'Turnstile script URL mismatch.' );
	fforms_captcha_providers_assert( 'https://challenges.cloudflare.com/turnstile/v0/siteverify' === $turnstile->verify_endpoint(), 'Turnstile verify endpoint mismatch.' );
	fforms_captcha_providers_assert( array( 'secret' => 'test-server-key', 'response' => 'tok', 'remoteip' => '1.2.3.4' ) === $turnstile->verify_params( 'tok', '1.2.3.4' ), 'Turnstile verify params mismatch.' );
	fforms_captcha_providers_assert( $turnstile->is_verified( array( 'success' => true ) ) && ! $turnstile->is_verified( array( 'success' => false ) ), 'Turnstile success must map from success === true.' );

	$smartcaptcha = new \FForms\Captcha\Yandex_Smartcaptcha();
	fforms_captcha_providers_assert( 'smartcaptcha' === $smartcaptcha->id(), 'SmartCaptcha id must be smartcaptcha.' );
	fforms_captcha_providers_assert( 'https://smartcaptcha.yandexcloud.net/captcha.js?render=explicit' === $smartcaptcha->script_url(), 'SmartCaptcha script URL mismatch.' );
	fforms_captcha_providers_assert( 'https://smartcaptcha.yandexcloud.net/validate' === $smartcaptcha->verify_endpoint(), 'SmartCaptcha verify endpoint mismatch.' );
	fforms_captcha_providers_assert( array( 'secret' => 'test-server-key', 'token' => 'tok', 'ip' => '1.2.3.4' ) === $smartcaptcha->verify_params( 'tok', '1.2.3.4' ), 'SmartCaptcha verify params must be secret/token/ip.' );
	fforms_captcha_providers_assert( $smartcaptcha->is_verified( array( 'status' => 'ok' ) ) && ! $smartcaptcha->is_verified( array( 'status' => 'failed' ) ), 'SmartCaptcha success must map from status === ok.' );

	// Case 8: the registry filter adds a fourth provider.
	add_filter(
		'fforms_captcha_providers',
		static function ( array $providers ): array {
			$providers['testprovider'] = FForms_Test_Captcha_Provider::class;
			return $providers;
		}
	);
	$registry = Manager::providers();
	fforms_captcha_providers_assert( isset( $registry['recaptcha'], $registry['turnstile'], $registry['smartcaptcha'], $registry['testprovider'] ), 'The registry must expose the three built-ins and the filtered fourth provider.' );
	fforms_captcha_providers_assert( $registry['testprovider'] instanceof \FForms\Captcha\Provider, 'The filtered provider must instantiate to a Provider.' );
	remove_all_filters( 'fforms_captcha_providers' );

	$form_id = wp_insert_post( array( 'post_type' => 'fform', 'post_status' => 'publish', 'post_title' => 'Captcha providers test' ), true );
	if ( is_wp_error( $form_id ) ) throw new RuntimeException( $form_id->get_error_message() );
	$created[] = (int) $form_id;
	update_post_meta( (int) $form_id, '_fforms_schema', wp_json_encode( array( 'fields' => array(
		array( 'name' => 'name', 'type' => 'text', 'required' => true ),
		array( 'name' => 'email', 'type' => 'email', 'required' => true ),
	) ) ) );
	$fields = array( 'name' => 'A', 'email' => 'a@example.test' );

	// Case 1: no provider configured — captcha stays off, no verification happens.
	$option = (array) $original_option;
	unset( $option['captcha'] );
	update_option( 'fforms_smtp', $option );
	remove_all_filters( 'fforms_captcha_required' );
	remove_all_filters( 'fforms_verify_captcha' );
	remove_all_filters( 'fforms_captcha_markup' );
	fforms_captcha_providers_assert( ! Manager::is_ready(), 'Without a configured provider is_ready() must be false.' );
	$response = $submit_form( (int) $form_id, $fields );
	fforms_captcha_providers_assert( $response instanceof WP_REST_Response && 201 === $response->get_status(), 'An unprotected form must accept a tokenless submission.' );
	$created[] = (int) $response->get_data()['entry_id'];
	fforms_captcha_providers_assert( 0 === count( $http_requests ), 'Verification must not be called while captcha is off.' );

	// Case 2: configured provider, empty token -> fforms_captcha_required 422.
	$configure( 'turnstile' );
	fforms_captcha_providers_assert( Manager::is_ready(), 'Turnstile with both keys must be ready.' );
	$response = $submit_form( (int) $form_id, $fields );
	fforms_captcha_providers_assert( is_wp_error( $response ) && 'fforms_captcha_required' === $response->get_error_code(), 'An empty token must return fforms_captcha_required.' );
	fforms_captcha_providers_assert( 422 === $response->get_error_data()['status'], 'A missing token must use HTTP 422.' );
	fforms_captcha_providers_assert( 0 === count( $http_requests ), 'An empty token must not reach the provider.' );

	// Case 3: provider accepts the token -> 201 and an entry.
	$http_behavior = 'success';
	$response      = $submit_form( (int) $form_id, $fields, 'valid-token' );
	fforms_captcha_providers_assert( $response instanceof WP_REST_Response && 201 === $response->get_status(), 'A verified token must store the entry.' );
	$created[] = (int) $response->get_data()['entry_id'];
	fforms_captcha_providers_assert( 1 === count( $http_requests ), 'Exactly one verification request must fire.' );
	fforms_captcha_providers_assert( 'https://challenges.cloudflare.com/turnstile/v0/siteverify' === $http_requests[0]['url'], 'The request must go to the Turnstile endpoint.' );
	fforms_captcha_providers_assert( array( 'secret' => 'test-server-key', 'response' => 'valid-token' ) === array_intersect_key( $http_requests[0]['args']['body'], array( 'secret' => '', 'response' => '' ) ), 'The request must carry the server key and the token.' );

	// Case 4: provider rejects the token -> fforms_captcha_failed with the provider code.
	$http_behavior = 'reject';
	$response      = $submit_form( (int) $form_id, $fields, 'bad-token' );
	fforms_captcha_providers_assert( is_wp_error( $response ) && 'fforms_captcha_failed' === $response->get_error_code(), 'A provider rejection must return fforms_captcha_failed.' );
	fforms_captcha_providers_assert( 'turnstile_rejected' === $response->get_error_data()['reason'], 'The reason must be turnstile_rejected.' );
	add_filter( 'fforms_captcha_fail_open', '__return_true' );
	$response = $submit_form( (int) $form_id, $fields, 'bad-token' );
	fforms_captcha_providers_assert( is_wp_error( $response ) && 'turnstile_rejected' === $response->get_error_data()['reason'], 'fail_open must never relax a provider rejection.' );
	remove_all_filters( 'fforms_captcha_fail_open' );

	// Case 5: network failure -> captcha_unavailable (fail-closed); fail_open passes it.
	$http_behavior = 'error';
	$response      = $submit_form( (int) $form_id, $fields, 'unreachable-token' );
	fforms_captcha_providers_assert( is_wp_error( $response ) && 'fforms_captcha_failed' === $response->get_error_code(), 'A network failure must reject the submission (fail-closed).' );
	fforms_captcha_providers_assert( 'captcha_unavailable' === $response->get_error_data()['reason'], 'The reason must be captcha_unavailable.' );
	$http_behavior = 'broken';
	$response      = $submit_form( (int) $form_id, $fields, 'unreachable-token' );
	fforms_captcha_providers_assert( is_wp_error( $response ) && 'captcha_unavailable' === $response->get_error_data()['reason'], 'An unparseable body must also be captcha_unavailable.' );
	add_filter( 'fforms_captcha_fail_open', '__return_true' );
	$http_behavior = 'error';
	$response      = $submit_form( (int) $form_id, $fields, 'unreachable-token' );
	fforms_captcha_providers_assert( $response instanceof WP_REST_Response && 201 === $response->get_status(), 'fail_open must let a network failure pass.' );
	$created[] = (int) $response->get_data()['entry_id'];
	remove_all_filters( 'fforms_captcha_fail_open' );

	// Case 6: apply_main governs only the /main route.
	$configure( 'turnstile', array( 'apply_main' => false ) );
	$response = $submit_main();
	fforms_captcha_providers_assert( $response instanceof WP_REST_Response && 201 === $response->get_status(), '/main must stay open while apply_main is off.' );
	$created[] = (int) $response->get_data()['entry_id'];
	$configure( 'turnstile', array( 'apply_main' => true ) );
	$response = $submit_main();
	fforms_captcha_providers_assert( is_wp_error( $response ) && 'fforms_captcha_required' === $response->get_error_code(), '/main must require a token while apply_main is on.' );
	$http_behavior = 'success';
	$response      = $submit_main( 'main-token' );
	fforms_captcha_providers_assert( $response instanceof WP_REST_Response && 201 === $response->get_status(), '/main must accept a verified token while apply_main is on.' );
	$created[] = (int) $response->get_data()['entry_id'];

	// Widget markup: container carries provider and sitekey, assets are queued.
	$form = \FForms\Form_Locator::resolve( (int) $form_id );
	fforms_captcha_providers_assert( $form instanceof \FForms\Form_Ref, 'The test form must resolve.' );
	$html = (string) apply_filters( 'fforms_captcha_markup', '', $form );
	fforms_captcha_providers_assert( str_contains( $html, 'data-provider="turnstile"' ), 'The widget container must carry the provider id.' );
	fforms_captcha_providers_assert( str_contains( $html, 'data-sitekey="test-client-key"' ), 'The widget container must carry the client key.' );
	fforms_captcha_providers_assert( wp_script_is( 'fforms-captcha-turnstile', 'enqueued' ), 'The provider script must be enqueued by the markup handler.' );
	fforms_captcha_providers_assert( wp_script_is( 'fforms-captcha', 'enqueued' ), 'The FForms captcha adapter must be enqueued by the markup handler.' );

	echo "FForms captcha provider checks passed.\n";
} finally {
	remove_all_filters( 'pre_http_request' );
	remove_all_filters( 'fforms_rate_limit' );
	remove_all_filters( 'fforms_captcha_fail_open' );
	remove_all_filters( 'fforms_captcha_providers' );
	remove_all_filters( 'fforms_captcha_required' );
	remove_all_filters( 'fforms_verify_captcha' );
	remove_all_filters( 'fforms_captcha_markup' );
	update_option( 'fforms_smtp', $original_option );
	foreach ( $created as $post_id ) wp_delete_post( $post_id, true );
}
