<?php
/**
 * Public submission and headless REST endpoints.
 *
 * @package FForms
 */

namespace FForms;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class REST_Controller {
	private const NAMESPACE = 'fforms/v1';

	public static function boot(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'form_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
					'fields'  => array( 'required' => true, 'type' => 'object' ),
					'website' => array( 'type' => 'string', 'default' => '' ),
					'source'  => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
		register_rest_route( self::NAMESPACE, '/forms', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'forms' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/forms/(?P<id>[\d]+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'form' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/forms/(?P<id>[\d]+)/schema', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'form_schema' ), 'permission_callback' => '__return_true' ) );
		register_rest_route(
			self::NAMESPACE,
			'/entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'entries' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
					'form_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
					'status'   => array( 'type' => 'string', 'enum' => array( 'new', 'read', 'replied', 'spam' ) ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/entries/(?P<id>[\d]+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'update_status' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array( 'status' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'new', 'read', 'replied', 'spam' ) ) ),
			)
		);
	}

	public static function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( strlen( $request->get_body() ) > (int) apply_filters( 'fforms_max_request_bytes', 262144 ) ) {
			return new WP_Error( 'fforms_payload_too_large', __( 'Запрос слишком большой.', 'fforms' ), array( 'status' => 413 ) );
		}

		$form_id = absint( $request['form_id'] );
		$form    = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type || 'publish' !== $form->post_status ) {
			return new WP_Error( 'fforms_form_not_found', __( 'Форма не найдена.', 'fforms' ), array( 'status' => 404 ) );
		}
		if ( '' !== trim( (string) $request['website'] ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => self::success_message( $form_id ) ), 200 );
		}

		$rate_error = self::check_rate_limit( $form_id, self::client_ip() );
		if ( is_wp_error( $rate_error ) ) {
			return $rate_error;
		}
		$fields = $request['fields'];
		if ( ! is_array( $fields ) ) {
			return new WP_Error( 'fforms_invalid_fields', __( 'Поле fields должно быть объектом.', 'fforms' ), array( 'status' => 400 ) );
		}

		$schema = Schema::normalize( (string) get_post_meta( $form_id, '_fforms_schema', true ) );
		$data   = Schema::validate_submission( $schema, $fields );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$entry_id = wp_insert_post(
			array( 'post_type' => Post_Types::ENTRY, 'post_status' => 'private', 'post_title' => sprintf( '%s — %s', get_the_title( $form_id ), current_time( 'Y-m-d H:i:s' ) ) ),
			true
		);
		if ( is_wp_error( $entry_id ) ) {
			return new WP_Error( 'fforms_entry_failed', __( 'Не удалось сохранить ответ.', 'fforms' ), array( 'status' => 500 ) );
		}

		$source = sanitize_text_field( (string) $request['source'] );
		if ( '' === $source ) {
			$source = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		}
		update_post_meta( $entry_id, '_fforms_form_id', $form_id );
		update_post_meta( $entry_id, '_fforms_data', (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $entry_id, '_fforms_status', 'new' );
		update_post_meta( $entry_id, '_fforms_source', $source );
		update_post_meta( $entry_id, '_fforms_ip', self::client_ip() );
		update_post_meta( $entry_id, '_fforms_user_agent', self::user_agent() );

		$notification_sent = Notifications::send( $form_id, (int) $entry_id, $data );
		do_action( 'fforms_entry_created', (int) $entry_id, $form_id, $data, $request );

		return new WP_REST_Response( array( 'success' => true, 'entry_id' => (int) $entry_id, 'message' => self::success_message( $form_id ), 'notification_sent' => $notification_sent ), 201 );
	}

	public static function forms(): WP_REST_Response {
		$posts = get_posts( array( 'post_type' => Post_Types::FORM, 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
		return new WP_REST_Response( array_map( array( self::class, 'prepare_form' ), $posts ) );
	}

	public static function form( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$form = self::public_form( absint( $request['id'] ) );
		return $form ?: new WP_Error( 'fforms_form_not_found', __( 'Форма не найдена.', 'fforms' ), array( 'status' => 404 ) );
	}

	public static function form_schema( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$form = self::public_form( absint( $request['id'] ) );
		return $form ? new WP_REST_Response( $form->get_data()['schema'] ) : new WP_Error( 'fforms_form_not_found', __( 'Форма не найдена.', 'fforms' ), array( 'status' => 404 ) );
	}

	public static function entries( WP_REST_Request $request ): WP_REST_Response {
		$meta_query = array();
		if ( $request['form_id'] ) {
			$meta_query[] = array( 'key' => '_fforms_form_id', 'value' => absint( $request['form_id'] ), 'compare' => '=' );
		}
		if ( $request['status'] ) {
			$meta_query[] = array( 'key' => '_fforms_status', 'value' => sanitize_key( $request['status'] ), 'compare' => '=' );
		}
		$query = new WP_Query(
			array( 'post_type' => Post_Types::ENTRY, 'post_status' => 'private', 'posts_per_page' => absint( $request['per_page'] ), 'paged' => absint( $request['page'] ), 'meta_query' => $meta_query )
		);
		$response = new WP_REST_Response( array_map( array( self::class, 'prepare_entry' ), $query->posts ) );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	public static function update_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$entry = get_post( absint( $request['id'] ) );
		if ( ! $entry || Post_Types::ENTRY !== $entry->post_type ) {
			return new WP_Error( 'fforms_entry_not_found', __( 'Ответ не найден.', 'fforms' ), array( 'status' => 404 ) );
		}
		$status = sanitize_key( (string) $request['status'] );
		update_post_meta( $entry->ID, '_fforms_status', $status );
		return new WP_REST_Response( array( 'id' => $entry->ID, 'status' => $status ) );
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	private static function public_form( int $form_id ): WP_REST_Response|false {
		$post = get_post( $form_id );
		return $post && Post_Types::FORM === $post->post_type && 'publish' === $post->post_status ? new WP_REST_Response( self::prepare_form( $post ) ) : false;
	}

	private static function prepare_form( \WP_Post $post ): array {
		return array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'type' => get_post_meta( $post->ID, '_fforms_type', true ) ?: 'contact', 'schema' => Schema::normalize( (string) get_post_meta( $post->ID, '_fforms_schema', true ) ), 'success_message' => self::success_message( $post->ID ), 'submit_url' => rest_url( self::NAMESPACE . '/submit' ) );
	}

	private static function prepare_entry( \WP_Post $post ): array {
		return array( 'id' => $post->ID, 'form_id' => (int) get_post_meta( $post->ID, '_fforms_form_id', true ), 'data' => json_decode( (string) get_post_meta( $post->ID, '_fforms_data', true ), true ) ?: array(), 'status' => get_post_meta( $post->ID, '_fforms_status', true ) ?: 'new', 'source' => (string) get_post_meta( $post->ID, '_fforms_source', true ), 'ip' => (string) get_post_meta( $post->ID, '_fforms_ip', true ), 'user_agent' => (string) get_post_meta( $post->ID, '_fforms_user_agent', true ), 'created_at' => get_post_time( DATE_ATOM, true, $post ) );
	}

	private static function check_rate_limit( int $form_id, string $ip ): true|WP_Error {
		$limit  = max( 1, (int) apply_filters( 'fforms_rate_limit', 5, $form_id ) );
		$window = max( 10, (int) apply_filters( 'fforms_rate_window', MINUTE_IN_SECONDS, $form_id ) );
		$key    = 'fforms_rate_' . md5( $form_id . '|' . $ip . '|' . wp_salt( 'nonce' ) );
		$count  = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new WP_Error( 'fforms_rate_limited', __( 'Слишком много отправок. Попробуйте позже.', 'fforms' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'fforms_client_ip', filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '' );
	}

	private static function user_agent(): string {
		$value = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 500 ) : substr( $value, 0, 500 );
	}

	private static function success_message( int $form_id ): string {
		return (string) get_post_meta( $form_id, '_fforms_success_message', true ) ?: __( 'Спасибо! Форма отправлена.', 'fforms' );
	}
}
