<?php
/**
 * WordPress integration checks for notification recipients.
 *
 * Run with: npx wp-env run cli wp eval-file wp-content/plugins/_fforms/tests/notifications.php
 */

use FForms\Form_Locator;
use FForms\Form_Ref;
use FForms\Notifications;
use FForms\REST_Controller;
use FForms\Settings;
use FForms\Registry\Main_Form;

/** @param mixed $actual @param mixed $expected */
function fforms_notification_assert_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . '\nExpected: ' . var_export( $expected, true ) . '\nActual: ' . var_export( $actual, true ) );
	}
}

function fforms_notification_form( string $to = '' ): Form_Ref {
	return new Form_Ref(
		post_id: 0,
		key: 'notification_test',
		title: 'Notification test',
		schema: array( 'fields' => array( array( 'name' => 'email', 'label' => 'Email' ) ) ),
		success_message: 'Thanks',
		origins: array(),
		notifications: array(
			'enabled'               => true,
			'to'                    => $to,
			'subject'               => '',
			'autoreply_enabled'     => false,
			'autoreply_email_field' => 'email',
			'autoreply_subject'     => '',
			'autoreply_message'     => '',
		),
		source: 'code',
		type: null
	);
}

$original_settings = get_option( Settings::OPTION, null );
$original_admin    = get_option( 'admin_email' );
$created_posts     = array();

try {
	update_option( 'admin_email', 'admin@example.test' );
	update_option(
		Settings::OPTION,
		array(
			'notifications'                    => true,
			'default_notification_recipients' => "default@example.test\ninvalid\nDEFAULT@example.test, second@example.test",
			'main_form_notifications'          => true,
		)
	);
	Main_Form::flush();

	fforms_notification_assert_same(
		array( 'override@example.test' ),
		Notifications::recipients( fforms_notification_form( "override@example.test\ninvalid, OVERRIDE@example.test" ) ),
		'A form-specific recipient must override and normalize the common list.'
	);
	fforms_notification_assert_same(
		array( 'default@example.test', 'second@example.test' ),
		Notifications::recipients( fforms_notification_form() ),
		'An empty form recipient list must inherit the normalized common list.'
	);

	update_option( Settings::OPTION, array( 'notifications' => true, 'default_notification_recipients' => '' ) );
	fforms_notification_assert_same(
		array( 'admin@example.test' ),
		Notifications::recipients( fforms_notification_form() ),
		'An empty common list must use the current WordPress administrator email.'
	);
	$invalid_admin = static fn(): string => 'not-an-email';
	add_filter( 'pre_option_admin_email', $invalid_admin );
	fforms_notification_assert_same(
		array(),
		Notifications::recipients( fforms_notification_form() ),
		'No valid fallback address must produce an empty recipient list.'
	);
	remove_filter( 'pre_option_admin_email', $invalid_admin );

	$legacy = Settings::sanitize(
		array(
			'notifications'              => true,
			'main_form_notifications'    => true,
			'main_form_notification_to'  => 'ignored@example.test',
		)
	);
	update_option( Settings::OPTION, array( 'main_form_notification_to' => 'migrated@example.test' ) );
	$migrated = Settings::sanitize( array( 'notifications' => true ) );
	fforms_notification_assert_same( 'migrated@example.test', $migrated['default_notification_recipients'], 'The legacy main recipient must migrate to the common setting.' );
	fforms_notification_assert_same( false, array_key_exists( 'main_form_notification_to', $migrated ), 'The migrated settings must not retain the legacy key.' );
	fforms_notification_assert_same( false, array_key_exists( 'main_form_notification_to', $legacy ), 'The sanitizer must never save a posted legacy key.' );
	update_option( Settings::OPTION, array( 'notifications' => true, 'default_notification_recipients' => 'default@example.test', 'host' => 'smtp.example.test', 'password' => 'secret' ) );
	$general_tab = Settings::sanitize( array( 'settings_tab' => 'general', 'notifications' => true ) );
	fforms_notification_assert_same( 'smtp.example.test', $general_tab['host'], 'Saving the General tab must retain the SMTP host.' );
	fforms_notification_assert_same( 'secret', $general_tab['password'], 'Saving the General tab must retain the SMTP password.' );
	$smtp_tab = Settings::sanitize( array( 'settings_tab' => 'smtp', 'host' => 'smtp-new.example.test' ) );
	fforms_notification_assert_same( true, $smtp_tab['notifications'], 'Saving the SMTP tab must retain notification settings.' );
	fforms_notification_assert_same( 'default@example.test', $smtp_tab['default_notification_recipients'], 'Saving the SMTP tab must retain default recipients.' );

	update_option( Settings::OPTION, array( 'notifications' => true, 'default_notification_recipients' => 'default@example.test', 'main_form_notifications' => true ) );
	$captured = array();
	$filter   = static function ( $pre, array $args ) use ( &$captured ) {
		$captured[] = $args;
		return true;
	};
	add_filter( 'pre_wp_mail', $filter, 10, 2 );
	fforms_notification_assert_same( true, Notifications::send( fforms_notification_form(), 123, array( 'email' => 'sender@example.test' ) ), 'wp_mail acceptance must be returned as notification_sent.' );
	fforms_notification_assert_same( array( 'default@example.test' ), $captured[0]['to'], 'wp_mail must receive the exact normalized recipient list.' );
	remove_filter( 'pre_wp_mail', $filter, 10 );

	$form_id = wp_insert_post( array( 'post_type' => 'fform', 'post_status' => 'publish', 'post_title' => 'Recipient CPT test' ), true );
	if ( is_wp_error( $form_id ) ) {
		throw new RuntimeException( $form_id->get_error_message() );
	}
	$created_posts[] = (int) $form_id;
	update_post_meta( (int) $form_id, '_fforms_notifications_enabled', true );
	update_post_meta( (int) $form_id, '_fforms_notification_to', 'cpt@example.test' );
	$cpt = Form_Locator::resolve( (int) $form_id );
	if ( is_wp_error( $cpt ) ) {
		throw new RuntimeException( $cpt->get_error_message() );
	}
	fforms_notification_assert_same( array( 'cpt@example.test' ), Notifications::recipients( $cpt ), 'A CPT recipient must override the common list.' );

	$result = fforms_add_api_route(
		'notification_test_code',
		array(
			'title'         => 'Recipient code test',
			'fields'        => array( array( 'name' => 'email', 'label' => 'Email', 'type' => 'email' ) ),
			'notifications' => array( 'enabled' => true, 'to' => '' ),
		)
	);
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	$code = Form_Locator::resolve( 'notification_test_code' );
	if ( is_wp_error( $code ) ) {
		throw new RuntimeException( $code->get_error_message() );
	}
	fforms_notification_assert_same( array( 'default@example.test' ), Notifications::recipients( $code ), 'A code form with no recipient must inherit the common list.' );

	$captured = array();
	$filter   = static function ( $pre, array $args ) use ( &$captured ) {
		$captured[] = $args;
		return true;
	};
	add_filter( 'pre_wp_mail', $filter, 10, 2 );
	Main_Form::flush();
	$request = new WP_REST_Request( 'POST', '/fforms/v1/main' );
	$request->set_body_params( array( 'email' => 'main-sender@example.test' ) );
	$response = REST_Controller::submit_main( $request );
	if ( is_wp_error( $response ) ) {
		throw new RuntimeException( $response->get_error_message() );
	}
	$created_posts[] = (int) $response->get_data()['entry_id'];
	fforms_notification_assert_same( 201, $response->get_status(), 'A valid /main submission must create an entry.' );
	fforms_notification_assert_same( true, $response->get_data()['notification_sent'], '/main must report wp_mail acceptance.' );
	fforms_notification_assert_same( array( 'default@example.test' ), $captured[0]['to'], '/main must use the common recipient list.' );
	$captured = array();
	$honeypot = new WP_REST_Request( 'POST', '/fforms/v1/main' );
	$honeypot->set_body_params( array( 'email' => 'bot@example.test', '_hp' => 'filled' ) );
	$honeypot_response = REST_Controller::submit_main( $honeypot );
	fforms_notification_assert_same( 200, $honeypot_response->get_status(), 'The /main honeypot must return a fake success.' );
	fforms_notification_assert_same( array(), $captured, 'The /main honeypot must not call wp_mail.' );
	remove_filter( 'pre_wp_mail', $filter, 10 );

	echo "FForms notification recipient checks passed.\n";
} finally {
	foreach ( $created_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( null === $original_settings ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings );
	}
	update_option( 'admin_email', $original_admin );
	Main_Form::flush();
}
