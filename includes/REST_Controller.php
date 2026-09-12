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
use WP_Term;

final class REST_Controller {
	private const NAMESPACE = 'fforms/v1';

	/**
	 * Top-level keys of POST /main that carry their own meaning. Everything
	 * else either matches a schema field or is kept as a custom field.
	 */
	private const MAIN_RESERVED_KEYS = array( 'formType', 'formId', 'form_type', 'form_id', 'customFields', 'meta', 'ref', 'userId', 'attachments', '_hp', 'source' );

	private const MAX_CUSTOM_FIELDS     = 20;
	private const MAX_CUSTOM_VALUE_LEN  = 2000;
	private const MAX_META_DEPTH        = 3;
	private const MAX_META_BYTES        = 8192;
	private const MAX_REF_LEN           = 200;

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
					'form_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
					'form_key' => array( 'type' => 'string', 'pattern' => '^[a-z0-9_]{1,32}$' ),
					'fields'   => array( 'required' => true, 'type' => 'object' ),
					'website'  => array( 'type' => 'string', 'default' => '' ),
					'source'   => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/main',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'submit_main' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route( self::NAMESPACE, '/forms', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'forms' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/forms/(?P<id>[\d]+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'form' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/forms/(?P<id>[\d]+)/schema', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'form_schema' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/forms/(?P<key>[a-z0-9_-]+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'form_by_key' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/forms/(?P<key>[a-z0-9_-]+)/schema', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( self::class, 'form_schema_by_key' ), 'permission_callback' => '__return_true' ) );
		register_rest_route(
			self::NAMESPACE,
			'/entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'entries' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'page'      => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page'  => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
					'form_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
					'form_key'  => array( 'type' => 'string', 'pattern' => '^[a-z0-9_]{1,32}$' ),
					'form_type' => array( 'type' => 'string', 'pattern' => '^[a-z0-9_-]{1,32}$' ),
					'status'    => array( 'type' => 'string', 'enum' => array( 'new', 'read', 'replied', 'spam' ) ),
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

	/**
	 * Strict contract used by the block, the shortcode and the public page:
	 * form_id/form_key plus a fields object, honeypot `website`.
	 */
	public static function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$too_large = self::reject_oversized_body( $request );
		if ( is_wp_error( $too_large ) ) {
			return $too_large;
		}

		$form = self::resolve_from_request( $request );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		if ( '' !== trim( (string) $request['website'] ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => $form->success_message ), 200 );
		}

		$fields = $request['fields'];
		if ( ! is_array( $fields ) ) {
			return new WP_Error( 'fforms_invalid_fields', __( 'The fields parameter must be an object.', 'fforms' ), array( 'status' => 400 ) );
		}

		return self::process( $form, $fields, $request );
	}

	/**
	 * Loose contract for headless frontends: a flat camelCase payload, no form
	 * to create up front, and a submission type that doubles as the form address.
	 */
	public static function submit_main( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$too_large = self::reject_oversized_body( $request );
		if ( is_wp_error( $too_large ) ) {
			return $too_large;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$attachments_error = self::reject_attachments( $request, $params );
		if ( is_wp_error( $attachments_error ) ) {
			return $attachments_error;
		}

		$reference = self::main_form_reference( $params );
		if ( is_wp_error( $reference ) ) {
			return $reference;
		}

		$form = '' === $reference ? Registry\Main_Form::ref() : Form_Locator::resolve( $reference );
		if ( is_wp_error( $form ) ) {
			// An unmatched value is a submission type, not a missing form.
			$form = Registry\Main_Form::ref();
		}

		$type_slug = self::main_form_type_slug( $form, $reference );
		if ( is_wp_error( $type_slug ) ) {
			return $type_slug;
		}

		// Honeypot is `_hp` here: `website` is a legitimate field name in a flat payload.
		if ( '' !== trim( (string) ( $params['_hp'] ?? '' ) ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => $form->success_message ), 200 );
		}

		$term = null;
		if ( '' !== $type_slug ) {
			$term = Form_Types::upsert( $type_slug );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
		}

		$fields = array();
		foreach ( $form->schema['fields'] as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( '' !== $name && array_key_exists( $name, $params ) ) {
				$fields[ $name ] = $params[ $name ];
			}
		}

		if ( 'builtin' === $form->source ) {
			$filled = array_filter(
				array( $fields['email'] ?? '', $fields['phone'] ?? '', $fields['message'] ?? '' ),
				static fn( $value ): bool => is_scalar( $value ) && '' !== trim( (string) $value )
			);
			if ( array() === $filled ) {
				return new WP_Error(
					'fforms_empty_submission',
					__( 'Fill in at least one of these fields: email, phone or message.', 'fforms' ),
					array( 'status' => 422 )
				);
			}
		}

		$extras = self::collect_extras( $params, $form );
		if ( is_wp_error( $extras ) ) {
			return $extras;
		}

		return self::process( $form, $fields, $request, $type_slug, $term, $extras );
	}

	/**
	 * Shared pipeline: rate limit → schema validation → entry → type term →
	 * mail → fforms_entry_created.
	 *
	 * @param array<string, mixed> $fields Raw values keyed by schema field name.
	 * @param array<string, mixed> $extras Off-schema data stored beside _fforms_data.
	 */
	private static function process( Form_Ref $form, array $fields, WP_REST_Request $request, string $type_slug = '', ?WP_Term $term = null, array $extras = array() ): WP_REST_Response|WP_Error {
		$rate_error = self::check_rate_limit( $form, self::client_ip() );
		if ( is_wp_error( $rate_error ) ) {
			return $rate_error;
		}

		$data = Schema::validate_submission( $form->schema, $fields );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// A form without its own type keeps today's behaviour: no term assigned.
		// Resolved before the insert so the entry title can carry the type.
		if ( '' === $type_slug && null === $term && null !== $form->type ) {
			$type_slug = (string) $form->type;
			$resolved  = Form_Types::upsert( $type_slug );
			$term      = $resolved instanceof WP_Term ? $resolved : null;
		}

		$entry_id = wp_insert_post(
			array( 'post_type' => Post_Types::ENTRY, 'post_status' => 'private', 'post_title' => self::entry_title( $form, $type_slug, $term ) ),
			true
		);
		if ( is_wp_error( $entry_id ) ) {
			return new WP_Error( 'fforms_entry_failed', __( 'Could not save the submission.', 'fforms' ), array( 'status' => 500 ) );
		}
		$entry_id = (int) $entry_id;

		$source = sanitize_text_field( (string) $request['source'] );
		if ( '' === $source ) {
			$source = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		}
		update_post_meta( $entry_id, '_fforms_form_id', $form->post_id );
		update_post_meta( $entry_id, '_fforms_form_key', (string) $form->key );
		update_post_meta( $entry_id, '_fforms_data', (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $entry_id, '_fforms_status', 'new' );
		update_post_meta( $entry_id, '_fforms_source', $source );
		update_post_meta( $entry_id, '_fforms_ip', self::client_ip() );
		update_post_meta( $entry_id, '_fforms_user_agent', self::user_agent() );
		self::store_extras( $entry_id, $extras );

		Form_Types::assign_to_entry( $entry_id, $type_slug, $term );

		$notification_sent = Notifications::send( $form, $entry_id, $data, $extras );
		do_action( 'fforms_entry_created', $entry_id, $form, $data, $request );

		$response = array( 'success' => true, 'entry_id' => $entry_id, 'message' => $form->success_message, 'notification_sent' => $notification_sent );
		if ( '' !== $type_slug ) {
			$response['form_type'] = $type_slug;
		}
		return new WP_REST_Response( $response, 201 );
	}

	/**
	 * The form type is what distinguishes entries that share one form — for the
	 * built-in /main endpoint it is the only thing that does — so it leads the
	 * title and stands in for the form name when present.
	 */
	private static function entry_title( Form_Ref $form, string $type_slug, ?WP_Term $term ): string {
		$label = $term instanceof WP_Term ? $term->name : $type_slug;
		if ( '' === $label ) {
			$label = $form->title;
		}

		return sprintf( '%s — %s', $label, current_time( 'Y-m-d H:i:s' ) );
	}

	public static function forms(): WP_REST_Response {
		$posts = get_posts( array( 'post_type' => Post_Types::FORM, 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array( self::prepare_form( Registry\Main_Form::ref() ) );
		foreach ( $posts as $post ) {
			$form = Form_Locator::resolve( $post->ID );
			if ( ! is_wp_error( $form ) ) {
				$items[] = self::prepare_form( $form );
			}
		}
		foreach ( Registry\Code_Forms::all() as $form ) {
			$items[] = self::prepare_form( $form );
		}
		return new WP_REST_Response( $items );
	}

	public static function form( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$form = Form_Locator::resolve( absint( $request['id'] ) );
		return is_wp_error( $form ) ? $form : new WP_REST_Response( self::prepare_form( $form ) );
	}

	public static function form_schema( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$form = Form_Locator::resolve( absint( $request['id'] ) );
		return is_wp_error( $form ) ? $form : new WP_REST_Response( $form->schema );
	}

	public static function form_by_key( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$form = Form_Locator::resolve( sanitize_key( (string) $request['key'] ) );
		return is_wp_error( $form ) ? $form : new WP_REST_Response( self::prepare_form( $form ) );
	}

	public static function form_schema_by_key( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$form = Form_Locator::resolve( sanitize_key( (string) $request['key'] ) );
		return is_wp_error( $form ) ? $form : new WP_REST_Response( $form->schema );
	}

	public static function entries( WP_REST_Request $request ): WP_REST_Response {
		$meta_query = array();
		if ( $request['form_id'] ) {
			$meta_query[] = array( 'key' => '_fforms_form_id', 'value' => absint( $request['form_id'] ), 'compare' => '=' );
		}
		if ( $request['form_key'] ) {
			$meta_query[] = array( 'key' => '_fforms_form_key', 'value' => sanitize_key( $request['form_key'] ), 'compare' => '=' );
		}
		if ( $request['status'] ) {
			$meta_query[] = array( 'key' => '_fforms_status', 'value' => sanitize_key( $request['status'] ), 'compare' => '=' );
		}
		$args = array( 'post_type' => Post_Types::ENTRY, 'post_status' => 'private', 'posts_per_page' => absint( $request['per_page'] ), 'paged' => absint( $request['page'] ), 'meta_query' => $meta_query );
		if ( $request['form_type'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => Form_Types::TAXONOMY, 'field' => 'slug', 'terms' => sanitize_key( (string) $request['form_type'] ) ) );
		}
		$query    = new WP_Query( $args );
		$response = new WP_REST_Response( array_map( array( self::class, 'prepare_entry' ), $query->posts ) );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	public static function update_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$entry = get_post( absint( $request['id'] ) );
		if ( ! $entry || Post_Types::ENTRY !== $entry->post_type ) {
			return new WP_Error( 'fforms_entry_not_found', __( 'Submission not found.', 'fforms' ), array( 'status' => 404 ) );
		}
		$status = sanitize_key( (string) $request['status'] );
		update_post_meta( $entry->ID, '_fforms_status', $status );
		return new WP_REST_Response( array( 'id' => $entry->ID, 'status' => $status ) );
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	private static function reject_oversized_body( WP_REST_Request $request ): true|WP_Error {
		if ( strlen( $request->get_body() ) > (int) apply_filters( 'fforms_max_request_bytes', 262144 ) ) {
			return new WP_Error( 'fforms_payload_too_large', __( 'The request is too large.', 'fforms' ), array( 'status' => 413 ) );
		}
		return true;
	}

	/**
	 * Attachments live in their own RFC (main-form-attachments); until then the
	 * route states plainly that files are not accepted.
	 *
	 * @param array<string, mixed> $params
	 */
	private static function reject_attachments( WP_REST_Request $request, array $params ): true|WP_Error {
		if ( array() !== $request->get_file_params() ) {
			return new WP_Error( 'fforms_attachments_disabled', __( 'Attachments are disabled.', 'fforms' ), array( 'status' => 400 ) );
		}
		if ( array_key_exists( 'attachments', $params ) && array() !== (array) $params['attachments'] ) {
			return new WP_Error( 'fforms_attachments_require_multipart', __( 'Attachments are only accepted as multipart/form-data.', 'fforms' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * formType/formId/form_type/form_id are aliases of one value; disagreeing
	 * aliases are a client bug, not something to guess at.
	 *
	 * @param array<string, mixed> $params
	 */
	private static function main_form_reference( array $params ): string|WP_Error {
		$values = array();
		foreach ( array( 'formType', 'formId', 'form_type', 'form_id' ) as $alias ) {
			if ( ! array_key_exists( $alias, $params ) ) {
				continue;
			}
			$value = is_scalar( $params[ $alias ] ) ? trim( (string) $params[ $alias ] ) : '';
			if ( '' !== $value ) {
				$values[] = $value;
			}
		}
		$values = array_values( array_unique( $values ) );
		if ( count( $values ) > 1 ) {
			return new WP_Error( 'fforms_form_ref_conflict', __( 'Several different formType/formId values were provided.', 'fforms' ), array( 'status' => 400 ) );
		}
		return $values[0] ?? '';
	}

	/**
	 * Addressing and classification are independent: a resolved form dictates
	 * its own type, an unmatched value becomes the type itself.
	 */
	private static function main_form_type_slug( Form_Ref $form, string $reference ): string|WP_Error {
		if ( 'post' === $form->source ) {
			return (string) ( $form->type ?? '' );
		}
		if ( 'code' === $form->source && null !== $form->type ) {
			return (string) $form->type;
		}
		return Form_Types::normalize( $reference );
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|WP_Error
	 */
	private static function collect_extras( array $params, Form_Ref $form ): array|WP_Error {
		$schema_names = array();
		foreach ( $form->schema['fields'] as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( '' !== $name ) {
				$schema_names[ $name ] = true;
			}
		}

		$custom = is_array( $params['customFields'] ?? null ) ? $params['customFields'] : array();
		foreach ( $params as $key => $value ) {
			if ( isset( $schema_names[ $key ] ) || in_array( $key, self::MAIN_RESERVED_KEYS, true ) || array_key_exists( $key, $custom ) ) {
				continue;
			}
			$custom[ $key ] = $value;
		}

		$meta = self::sanitize_meta( $params['meta'] ?? null );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		return array(
			'custom'  => self::sanitize_custom_fields( $custom ),
			'meta'    => $meta,
			'ref'     => self::truncate( sanitize_text_field( (string) ( is_scalar( $params['ref'] ?? null ) ? $params['ref'] : '' ) ), self::MAX_REF_LEN ),
			'user_id' => absint( $params['userId'] ?? 0 ),
		);
	}

	/**
	 * Off-schema data never enters _fforms_data — the "passed server validation"
	 * boundary stays sharp.
	 *
	 * @param array<string, mixed> $extras
	 */
	private static function store_extras( int $entry_id, array $extras ): void {
		if ( array() === $extras ) {
			return;
		}
		if ( array() !== ( $extras['custom'] ?? array() ) ) {
			update_post_meta( $entry_id, '_fforms_custom', (string) wp_json_encode( $extras['custom'], JSON_UNESCAPED_UNICODE ) );
		}
		if ( array() !== ( $extras['meta'] ?? array() ) ) {
			update_post_meta( $entry_id, '_fforms_meta', (string) wp_json_encode( $extras['meta'], JSON_UNESCAPED_UNICODE ) );
		}
		if ( '' !== ( $extras['ref'] ?? '' ) ) {
			update_post_meta( $entry_id, '_fforms_ref', $extras['ref'] );
		}
		if ( ! empty( $extras['user_id'] ) ) {
			update_post_meta( $entry_id, '_fforms_user_id', (int) $extras['user_id'] );
		}
	}

	/**
	 * @param array<string, mixed> $custom
	 * @return array<string, string>
	 */
	private static function sanitize_custom_fields( array $custom ): array {
		$clean = array();
		foreach ( $custom as $key => $value ) {
			if ( count( $clean ) >= self::MAX_CUSTOM_FIELDS ) {
				break;
			}
			$name = sanitize_key( (string) $key );
			if ( '' === $name || ! is_scalar( $value ) ) {
				continue;
			}
			$clean[ $name ] = self::truncate( sanitize_text_field( (string) $value ), self::MAX_CUSTOM_VALUE_LEN );
		}
		return $clean;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function sanitize_meta( mixed $meta ): array|WP_Error {
		if ( ! is_array( $meta ) || array() === $meta ) {
			return array();
		}
		$encoded = wp_json_encode( $meta, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_META_BYTES || self::array_depth( $meta ) > self::MAX_META_DEPTH ) {
			return new WP_Error(
				'fforms_meta_too_large',
				__( 'The meta field is too large or nested too deeply.', 'fforms' ),
				array( 'status' => 422 )
			);
		}
		return $meta;
	}

	/** @param array<mixed> $value */
	private static function array_depth( array $value ): int {
		$depth = 1;
		foreach ( $value as $item ) {
			if ( is_array( $item ) ) {
				$depth = max( $depth, self::array_depth( $item ) + 1 );
			}
		}
		return $depth;
	}

	private static function truncate( string $value, int $limit ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	private static function resolve_from_request( WP_REST_Request $request ): Form_Ref|WP_Error {
		$form_id  = absint( $request['form_id'] ?? 0 );
		$form_key = sanitize_key( (string) ( $request['form_key'] ?? '' ) );
		if ( ! $form_id && '' === $form_key ) {
			return new WP_Error( 'fforms_form_ref_required', __( 'Provide form_id or form_key.', 'fforms' ), array( 'status' => 400 ) );
		}
		return Form_Locator::resolve( $form_id ?: $form_key );
	}

	private static function prepare_form( Form_Ref $form ): array {
		return array(
			'id'              => $form->post_id,
			'key'             => $form->key,
			'source'          => $form->source,
			'mode'            => 'post' === $form->source ? Post_Types::form_mode( $form->post_id ) : 'headless',
			'title'           => $form->title,
			'type'            => 'post' === $form->source ? ( get_post_meta( $form->post_id, '_fforms_type', true ) ?: 'contact' ) : 'contact',
			'form_type'       => $form->type,
			'schema'          => $form->schema,
			'success_message' => $form->success_message,
			'submit_url'      => 'builtin' === $form->source ? rest_url( self::NAMESPACE . '/main' ) : rest_url( self::NAMESPACE . '/submit' ),
		);
	}

	private static function prepare_entry( \WP_Post $post ): array {
		return array(
			'id'            => $post->ID,
			'form_id'       => (int) get_post_meta( $post->ID, '_fforms_form_id', true ),
			'form_key'      => (string) get_post_meta( $post->ID, '_fforms_form_key', true ),
			'form_type'     => Form_Types::entry_type_slug( $post->ID ),
			'data'          => json_decode( (string) get_post_meta( $post->ID, '_fforms_data', true ), true ) ?: array(),
			'custom_fields' => json_decode( (string) get_post_meta( $post->ID, '_fforms_custom', true ), true ) ?: array(),
			'meta'          => json_decode( (string) get_post_meta( $post->ID, '_fforms_meta', true ), true ) ?: array(),
			'ref'           => (string) get_post_meta( $post->ID, '_fforms_ref', true ),
			'user_id'       => (int) get_post_meta( $post->ID, '_fforms_user_id', true ),
			'status'        => get_post_meta( $post->ID, '_fforms_status', true ) ?: 'new',
			'source'        => (string) get_post_meta( $post->ID, '_fforms_source', true ),
			'ip'            => (string) get_post_meta( $post->ID, '_fforms_ip', true ),
			'user_agent'    => (string) get_post_meta( $post->ID, '_fforms_user_agent', true ),
			'created_at'    => get_post_time( DATE_ATOM, true, $post ),
		);
	}

	private static function check_rate_limit( Form_Ref $form, string $ip ): true|WP_Error {
		$rate_ref = $form->rate_key();
		$limit    = max( 1, (int) apply_filters( 'fforms_rate_limit', 5, $rate_ref ) );
		$window   = max( 10, (int) apply_filters( 'fforms_rate_window', MINUTE_IN_SECONDS, $rate_ref ) );
		$key      = 'fforms_rate_' . md5( $rate_ref . '|' . $ip . '|' . wp_salt( 'nonce' ) );
		$count    = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new WP_Error( 'fforms_rate_limited', __( 'Too many submissions. Try again later.', 'fforms' ), array( 'status' => 429 ) );
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
}
