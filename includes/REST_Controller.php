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
	 * Top-level keys of POST /main that carry their own meaning. Every other
	 * key is a form field and is stored exactly as it arrived.
	 */
	private const MAIN_RESERVED_KEYS = array( 'formType', 'formId', 'form_type', 'form_id', 'meta', 'ref', 'userId', 'attachments', '_hp', 'source' );

	/** Type assigned when a /main submission names none, so every entry is filterable. */
	private const MAIN_DEFAULT_TYPE = 'main';

	private const MAX_MAIN_FIELDS      = 50;
	private const MAX_MAIN_VALUE_LEN   = 2000;
	private const MAX_META_DEPTH       = 3;
	private const MAX_META_BYTES       = 8192;
	private const MAX_REF_LEN          = 200;

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
			'/forms/(?P<id>[\d]+)/share-token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'reissue_share_token' ),
				'permission_callback' => array( self::class, 'can_edit_form' ),
			)
		);
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
	 * Schema-free contract for headless frontends: a flat payload whose keys are
	 * whatever the integration sends, plus a form type that classifies the entry.
	 * Submissions always land in the built-in main form — `formType` addresses
	 * nothing, it only labels.
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

		$type_slug = '' === $reference ? self::MAIN_DEFAULT_TYPE : Form_Types::normalize( $reference );
		if ( is_wp_error( $type_slug ) ) {
			return $type_slug;
		}

		$form = Registry\Main_Form::ref();

		// Honeypot is `_hp` here: `website` is a legitimate field name in a flat payload.
		if ( '' !== trim( (string) ( $params['_hp'] ?? '' ) ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => $form->success_message ), 200 );
		}

		$fields = self::main_fields( $params );
		if ( array() === array_filter( $fields, static fn( string $value ): bool => '' !== trim( $value ) ) ) {
			return new WP_Error(
				'fforms_empty_submission',
				__( 'Provide at least one non-empty field.', 'fforms' ),
				array( 'status' => 422 )
			);
		}

		$term = Form_Types::upsert( $type_slug );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$extras = self::collect_extras( $params );
		if ( is_wp_error( $extras ) ) {
			return $extras;
		}

		return self::process( $form, $fields, $request, $type_slug, $term, $extras, false );
	}

	/**
	 * Everything that is not a reserved key is a field: the key is normalized,
	 * the value is sanitized, and both are stored as sent. Nothing is dropped
	 * for failing a type check, because this route declares no types.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, string>
	 */
	private static function main_fields( array $params ): array {
		$fields = array();
		foreach ( $params as $key => $value ) {
			if ( in_array( (string) $key, self::MAIN_RESERVED_KEYS, true ) ) {
				continue;
			}
			if ( count( $fields ) >= self::MAX_MAIN_FIELDS ) {
				break;
			}
			$name = sanitize_key( (string) $key );
			if ( '' === $name || isset( $fields[ $name ] ) ) {
				continue;
			}
			$fields[ $name ] = self::main_value( $name, $value );
		}

		return $fields;
	}

	/**
	 * Scalars are sanitized text; anything structured is kept as its JSON so the
	 * submission is never silently truncated to an empty value.
	 */
	private static function main_value( string $name, mixed $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( ! is_scalar( $value ) ) {
			$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
			return self::truncate( is_string( $encoded ) ? $encoded : '', self::MAX_MAIN_VALUE_LEN );
		}
		if ( is_bool( $value ) ) {
			$value = $value ? '1' : '';
		}

		$clean = self::truncate( sanitize_text_field( (string) $value ), self::MAX_MAIN_VALUE_LEN );
		if ( 'email' !== $name ) {
			return $clean;
		}

		// Normalize the address, but keep an unparseable value rather than losing
		// it: an invalid address only means the auto-reply is not sent.
		$email = sanitize_email( $clean );
		return '' !== $email ? $email : $clean;
	}

	/**
	 * Shared pipeline: rate limit → schema validation → entry → type term →
	 * mail → fforms_entry_created.
	 *
	 * @param array<string, mixed> $fields   Raw values keyed by field name.
	 * @param array<string, mixed> $extras   Off-schema data stored beside _fforms_data.
	 * @param bool                 $validate Whether the form declares a schema to validate against.
	 */
	private static function process( Form_Ref $form, array $fields, WP_REST_Request $request, string $type_slug = '', ?WP_Term $term = null, array $extras = array(), bool $validate = true ): WP_REST_Response|WP_Error {
		$rate_error = self::check_rate_limit( $form, self::client_ip() );
		if ( is_wp_error( $rate_error ) ) {
			return $rate_error;
		}

		$data = $validate ? Schema::validate_submission( $form->schema, $fields ) : $fields;
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
		update_post_meta( $entry_id, '_fforms_data', self::encode_meta( $data ) );
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

	/**
	 * Reissuing invalidates the link everyone already has, so it is deliberately
	 * a write, restricted to whoever may edit the form.
	 */
	public static function reissue_share_token( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$form_id = absint( $request['id'] );
		$form    = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type ) {
			return new WP_Error( 'fforms_form_not_found', __( 'Form not found.', 'fforms' ), array( 'status' => 404 ) );
		}

		$token = Public_Form::regenerate_token( $form_id );
		return new WP_REST_Response(
			array(
				'token'          => $token,
				'url'            => Public_Form::url( $form_id ),
				'embed_url'      => Public_Form::embed_url( $form_id ),
				'iframe_snippet' => Public_Form::iframe_snippet( $form_id ),
				'script_snippet' => Public_Form::script_snippet( $form_id ),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function can_edit_form( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_post', absint( $request['id'] ) );
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
	 * Context that is not part of the submission itself. Ordinary fields no
	 * longer pass through here: they go straight into _fforms_data.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|WP_Error
	 */
	private static function collect_extras( array $params ): array|WP_Error {
		$meta = self::sanitize_meta( $params['meta'] ?? null );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		return array(
			'meta'    => $meta,
			'ref'     => self::truncate( sanitize_text_field( (string) ( is_scalar( $params['ref'] ?? null ) ? $params['ref'] : '' ) ), self::MAX_REF_LEN ),
			'user_id' => absint( $params['userId'] ?? 0 ),
		);
	}

	/**
	 * Request context stored beside the submission. `_fforms_custom` is no longer
	 * written; it stays readable for entries created before /main dropped its schema.
	 *
	 * @param array<string, mixed> $extras
	 */
	private static function store_extras( int $entry_id, array $extras ): void {
		if ( array() === $extras ) {
			return;
		}
		if ( array() !== ( $extras['meta'] ?? array() ) ) {
			update_post_meta( $entry_id, '_fforms_meta', self::encode_meta( $extras['meta'] ) );
		}
		if ( '' !== ( $extras['ref'] ?? '' ) ) {
			update_post_meta( $entry_id, '_fforms_ref', $extras['ref'] );
		}
		if ( ! empty( $extras['user_id'] ) ) {
			update_post_meta( $entry_id, '_fforms_user_id', (int) $extras['user_id'] );
		}
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

	/**
	 * update_metadata() unslashes what it is given, which would strip the
	 * backslashes JSON uses to escape quotes and leave unparseable meta behind.
	 * Slashing here means the value reaches the database exactly as encoded.
	 *
	 * @param array<string, mixed> $value
	 */
	private static function encode_meta( array $value ): string {
		return wp_slash( (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) );
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
			'share_link'      => 'post' === $form->source && Public_Form::is_enabled( $form->post_id ),
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
