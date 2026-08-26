<?php
/**
 * Dynamic Gutenberg form block.
 *
 * @package FForms
 */

namespace FForms;

final class Block {
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ), 20 );
	}

	public static function register(): void {
		wp_register_script( 'fforms-block-editor', FFORMS_URL . 'assets/block-editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-block-editor' ), self::asset_version( 'assets/block-editor.js' ), true );
		wp_register_script( 'fforms-view', FFORMS_URL . 'assets/view.js', array(), self::asset_version( 'assets/view.js' ), true );
		wp_register_style( 'fforms-view', FFORMS_URL . 'assets/view.css', array(), self::asset_version( 'assets/view.css' ) );

		register_block_type(
			'fforms/form',
			array(
				'api_version' => 3, 'editor_script' => 'fforms-block-editor', 'render_callback' => array( self::class, 'render' ),
				'attributes' => array( 'formId' => array( 'type' => 'integer', 'default' => 0 ), 'showTitle' => array( 'type' => 'boolean', 'default' => false ), 'submitLabel' => array( 'type' => 'string', 'default' => '' ) ),
				'supports' => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
			)
		);
	}

	public static function render( array $attributes ): string {
		$form_id = absint( $attributes['formId'] ?? 0 );
		$form    = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type || 'publish' !== $form->post_status ) {
			return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'Выберите опубликованную форму в настройках блока.', 'fforms' ) . '</p>' : '';
		}

		wp_enqueue_script( 'fforms-view' );
		wp_enqueue_style( 'fforms-view' );
		$schema    = Schema::normalize( (string) get_post_meta( $form_id, '_fforms_schema', true ) );
		$form_html = '';
		$unique_id = wp_unique_id( 'fforms-' . $form_id . '-' );
		foreach ( $schema['fields'] as $index => $field ) {
			$form_html .= self::render_field( $field, $unique_id . '-' . $index );
		}

		$title        = ! empty( $attributes['showTitle'] ) ? '<h2 class="fforms-title">' . esc_html( get_the_title( $form ) ) . '</h2>' : '';
		$submit_label = sanitize_text_field( (string) ( $attributes['submitLabel'] ?? '' ) ) ?: __( 'Отправить', 'fforms' );
		$wrapper      = get_block_wrapper_attributes( array( 'class' => 'fforms' ) );

		return sprintf(
			'<div %1$s>%2$s<form id="%3$s" class="fforms-form" data-endpoint="%4$s" data-form-id="%5$d"><div class="fforms-fields">%6$s</div><div class="fforms-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><button class="fforms-submit" type="submit">%7$s</button><div class="fforms-response" role="status" aria-live="polite"></div></form><noscript>%8$s</noscript></div>',
			$wrapper, $title, esc_attr( $unique_id ), esc_url( rest_url( 'fforms/v1/submit' ) ), $form_id, $form_html, esc_html( $submit_label ), esc_html__( 'Для отправки формы включите JavaScript.', 'fforms' )
		);
	}

	private static function render_field( array $field, string $id ): string {
		$name        = $field['name'];
		$type        = $field['type'];
		$required    = ! empty( $field['required'] );
		$required_at = $required ? ' required aria-required="true"' : '';
		$placeholder = '' !== $field['placeholder'] ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
		$label       = esc_html( $field['label'] ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' );
		$name_attr   = 'fields[' . $name . ']';

		if ( 'hidden' === $type ) {
			return '<input type="hidden" name="' . esc_attr( $name_attr ) . '" value="">';
		}
		if ( 'textarea' === $type ) {
			return '<div class="fforms-field"><label for="' . esc_attr( $id ) . '">' . $label . '</label><textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_attr ) . '"' . $placeholder . $required_at . '></textarea></div>';
		}
		if ( 'select' === $type ) {
			$options = '<option value="">' . esc_html__( 'Выберите вариант', 'fforms' ) . '</option>';
			foreach ( $field['options'] ?? array() as $option ) {
				$options .= '<option value="' . esc_attr( $option['value'] ) . '">' . esc_html( $option['label'] ) . '</option>';
			}
			return '<div class="fforms-field"><label for="' . esc_attr( $id ) . '">' . $label . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_attr ) . '"' . $required_at . '>' . $options . '</select></div>';
		}
		if ( in_array( $type, array( 'radio', 'checkbox' ), true ) && ! empty( $field['options'] ) ) {
			$options = '';
			foreach ( $field['options'] as $option_index => $option ) {
				$option_id = $id . '-' . $option_index;
				$multiple  = 'checkbox' === $type ? '[]' : '';
				$choice_required = 'radio' === $type ? $required_at : '';
				$options  .= '<label class="fforms-choice" for="' . esc_attr( $option_id ) . '"><input id="' . esc_attr( $option_id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name_attr . $multiple ) . '" value="' . esc_attr( $option['value'] ) . '"' . $choice_required . '> ' . esc_html( $option['label'] ) . '</label>';
			}
			return '<fieldset class="fforms-field fforms-options"><legend>' . $label . '</legend>' . $options . '</fieldset>';
		}
		if ( 'checkbox' === $type ) {
			return '<div class="fforms-field"><label class="fforms-choice" for="' . esc_attr( $id ) . '"><input id="' . esc_attr( $id ) . '" type="checkbox" name="' . esc_attr( $name_attr ) . '" value="1"' . $required_at . '> ' . $label . '</label></div>';
		}

		$input_type = in_array( $type, array( 'email', 'tel', 'url', 'number' ), true ) ? $type : 'text';
		return '<div class="fforms-field"><label for="' . esc_attr( $id ) . '">' . $label . '</label><input id="' . esc_attr( $id ) . '" type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name_attr ) . '"' . $placeholder . $required_at . '></div>';
	}

	private static function asset_version( string $relative_path ): string {
		$path = FFORMS_DIR . $relative_path;
		return file_exists( $path ) ? (string) filemtime( $path ) : FFORMS_VERSION;
	}
}
