<?php
/** Dynamic render callbacks for FForms blocks. @package FForms */
namespace FForms\Blocks;

use FForms\Post_Types;
use FForms\Schema;
use FForms\Schema\Schema_Repository;
use WP_Block;

final class Form_Renderer {
	private static array $resolving = array();
	private static int $source_form_id = 0;

	/** @param array<string,mixed> $attributes */
	public static function render( array $attributes, string $content, WP_Block $block ): string {
		$form_id = absint( $attributes['ref'] ?? $attributes['formId'] ?? 0 );
		if ( $form_id ) return self::render_reference( $form_id );
		return self::render_shell( self::$source_form_id ?: absint( $block->context['postId'] ?? 0 ), $content, $attributes );
	}

	/** @param array<string,mixed> $attributes */
	public static function render_field( array $attributes, string $block_name ): string {
		$type = str_replace( 'fforms/field-', '', $block_name );
		if ( ! Schema::is_supported_type( $type ) ) return '';
		$field = array( 'name' => sanitize_key( (string) ( $attributes['name'] ?? '' ) ), 'label' => sanitize_text_field( (string) ( $attributes['label'] ?? '' ) ), 'type' => $type, 'required' => ! empty( $attributes['required'] ), 'placeholder' => sanitize_text_field( (string) ( $attributes['placeholder'] ?? '' ) ), 'max_length' => absint( $attributes['maxLength'] ?? 0 ), 'options' => Schema::normalize_options( $attributes['options'] ?? array() ) );
		return '' === $field['name'] ? '' : self::field_markup( $field, wp_unique_id( 'fforms-control-' ) );
	}

	/** @param array<string,mixed> $attributes */
	public static function render_submit( array $attributes ): string {
		$label = sanitize_text_field( (string) ( $attributes['label'] ?? '' ) ) ?: __( 'Отправить', 'fforms' );
		return '<button class="fforms-submit" type="submit" data-wp-bind--disabled="context.isSubmitting">' . esc_html( $label ) . '</button>';
	}

	private static function render_reference( int $form_id ): string {
		$form = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type || 'publish' !== $form->post_status ) return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'Выберите опубликованную форму в настройках блока.', 'fforms' ) . '</p>' : '';
		if ( isset( self::$resolving[ $form_id ] ) ) return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'Обнаружена циклическая ссылка формы.', 'fforms' ) . '</p>' : '';
		self::$resolving[ $form_id ] = true;
		$previous = self::$source_form_id;
		self::$source_form_id = $form_id;
		try {
			if ( ! \FForms\Schema\Schema_Compiler::has_form_block( $form->post_content ) ) {
				$schema = Schema_Repository::for_form( $form_id );
				return is_wp_error( $schema ) ? '' : self::render_legacy( $form_id, $schema );
			}
			return do_blocks( $form->post_content );
		} finally {
			self::$source_form_id = $previous;
			unset( self::$resolving[ $form_id ] );
		}
	}

	/** @param array{fields:array<int,array<string,mixed>>} $schema */
	private static function render_legacy( int $form_id, array $schema ): string {
		$content = '';
		foreach ( $schema['fields'] as $field ) $content .= self::field_markup( $field, wp_unique_id( 'fforms-control-' ) );
		return self::render_shell( $form_id, $content . self::render_submit( array() ), array() );
	}

	/** @param array<string,mixed> $attributes */
	private static function render_shell( int $form_id, string $content, array $attributes ): string {
		if ( ! $form_id || Post_Types::FORM !== get_post_type( $form_id ) ) return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'Сохраните форму и вставьте её ссылку на страницу.', 'fforms' ) . '</p>' : '';
		$context = (string) wp_json_encode( array( 'formId' => $form_id, 'endpoint' => rest_url( 'fforms/v1/submit' ), 'isSubmitting' => false, 'isError' => false, 'message' => '' ) );
		$title = ! empty( $attributes['showTitle'] ) ? '<h2 class="fforms-title">' . esc_html( get_the_title( $form_id ) ) . '</h2>' : '';
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'fforms' ) );
		return '<div ' . $wrapper . '>' . $title . '<form class="fforms-form" data-wp-interactive="fforms/form" data-wp-context="' . esc_attr( $context ) . '" data-wp-on--submit="actions.submit" data-wp-bind--aria-busy="context.isSubmitting"><div class="fforms-fields">' . $content . '</div><div class="fforms-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><div class="fforms-response" role="status" aria-live="polite" data-wp-text="context.message" data-wp-class--is-error="context.isError"></div></form></div>';
	}

	/** @param array<string,mixed> $field */
	private static function field_markup( array $field, string $id ): string {
		$name = $field['name']; $type = $field['type']; $required = ! empty( $field['required'] );
		$required_a = $required ? ' required aria-required="true"' : '';
		$maxlength = ! empty( $field['max_length'] ) ? ' maxlength="' . absint( $field['max_length'] ) . '"' : '';
		$placeholder = '' !== (string) ( $field['placeholder'] ?? '' ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
		$label = esc_html( $field['label'] ?: $name ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' );
		$name_attr = 'fields[' . $name . ']'; $error_id = $id . '-error';
		$error = '<p id="' . esc_attr( $error_id ) . '" class="fforms-field-error" data-fforms-error="' . esc_attr( $name ) . '" role="alert"></p>';
		$described = ' aria-describedby="' . esc_attr( $error_id ) . '" data-fforms-field="' . esc_attr( $name ) . '"';
		if ( 'hidden' === $type ) return '<input type="hidden" name="' . esc_attr( $name_attr ) . '" value="" data-fforms-field="' . esc_attr( $name ) . '">';
		if ( 'textarea' === $type ) return '<div class="fforms-field"><label for="' . esc_attr( $id ) . '">' . $label . '</label><textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_attr ) . '"' . $placeholder . $maxlength . $required_a . $described . '></textarea>' . $error . '</div>';
		if ( 'select' === $type ) {
			$options = '<option value="">' . esc_html__( 'Выберите вариант', 'fforms' ) . '</option>';
			foreach ( $field['options'] ?? array() as $option ) $options .= '<option value="' . esc_attr( $option['value'] ) . '">' . esc_html( $option['label'] ) . '</option>';
			return '<div class="fforms-field"><label for="' . esc_attr( $id ) . '">' . $label . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_attr ) . '"' . $required_a . $described . '>' . $options . '</select>' . $error . '</div>';
		}
		if ( in_array( $type, array( 'radio', 'checkbox' ), true ) && ! empty( $field['options'] ) ) {
			$options = '';
			foreach ( $field['options'] as $index => $option ) { $option_id = $id . '-' . $index; $multiple = 'checkbox' === $type ? '[]' : ''; $options .= '<label class="fforms-choice" for="' . esc_attr( $option_id ) . '"><input id="' . esc_attr( $option_id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name_attr . $multiple ) . '" value="' . esc_attr( $option['value'] ) . '"' . ( 'radio' === $type ? $required_a : '' ) . $described . '> ' . esc_html( $option['label'] ) . '</label>'; }
			return '<fieldset class="fforms-field fforms-options"><legend>' . $label . '</legend>' . $options . $error . '</fieldset>';
		}
		if ( 'checkbox' === $type ) return '<div class="fforms-field"><label class="fforms-choice" for="' . esc_attr( $id ) . '"><input id="' . esc_attr( $id ) . '" type="checkbox" name="' . esc_attr( $name_attr ) . '" value="1"' . $required_a . $described . '> ' . $label . '</label>' . $error . '</div>';
		$input_type = in_array( $type, array( 'email', 'tel', 'url', 'number' ), true ) ? $type : 'text';
		return '<div class="fforms-field"><label for="' . esc_attr( $id ) . '">' . $label . '</label><input id="' . esc_attr( $id ) . '" type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name_attr ) . '"' . $placeholder . $maxlength . $required_a . $described . '>' . $error . '</div>';
	}
}
