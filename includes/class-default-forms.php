<?php
/**
 * Seeds the default contact and lead forms on plugin activation.
 *
 * @package FForms
 */

namespace FForms;

final class Default_Forms {
	private const SEED_META_KEY = '_fforms_seed_key';

	public static function seed(): void {
		self::maybe_create(
			'contact',
			__( 'Контактная форма', 'fforms' ),
			'contact',
			array(
				array( 'block' => 'fforms/field-text', 'name' => 'name', 'label' => __( 'Имя', 'fforms' ), 'required' => true ),
				array( 'block' => 'fforms/field-email', 'name' => 'email', 'label' => __( 'Email', 'fforms' ), 'required' => true ),
				array( 'block' => 'fforms/field-textarea', 'name' => 'message', 'label' => __( 'Сообщение', 'fforms' ), 'required' => true ),
			),
			__( 'Отправить', 'fforms' )
		);

		self::maybe_create(
			'lead',
			__( 'Форма для лидов', 'fforms' ),
			'lead',
			array(
				array( 'block' => 'fforms/field-text', 'name' => 'name', 'label' => __( 'Имя', 'fforms' ), 'required' => true ),
				array( 'block' => 'fforms/field-tel', 'name' => 'phone', 'label' => __( 'Телефон', 'fforms' ), 'required' => true ),
				array( 'block' => 'fforms/field-email', 'name' => 'email', 'label' => __( 'Email', 'fforms' ), 'required' => false ),
			),
			__( 'Оставить заявку', 'fforms' )
		);
	}

	/** @param array<int, array<string, mixed>> $fields */
	private static function maybe_create( string $seed_key, string $title, string $type, array $fields, string $submit_label ): void {
		$existing = get_posts(
			array(
				'post_type'      => Post_Types::FORM,
				'post_status'    => 'any',
				'meta_key'       => self::SEED_META_KEY,
				'meta_value'     => $seed_key,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $existing ) ) {
			return;
		}

		$inner_blocks = array();
		foreach ( $fields as $field ) {
			$inner_blocks[] = self::block(
				(string) $field['block'],
				array(
					'fieldId'  => $field['name'],
					'name'     => $field['name'],
					'label'    => $field['label'],
					'required' => ! empty( $field['required'] ),
				)
			);
		}
		$inner_blocks[] = self::block( 'fforms/submit', array( 'label' => $submit_label ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => Post_Types::FORM,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => serialize_block( self::block( 'fforms/form', array(), $inner_blocks ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		update_post_meta( $post_id, self::SEED_META_KEY, $seed_key );
		update_post_meta( $post_id, '_fforms_type', $type );
	}

	/**
	 * @param array<string, mixed>            $attrs
	 * @param array<int, array<string, mixed>> $inner_blocks
	 * @return array<string, mixed>
	 */
	private static function block( string $name, array $attrs, array $inner_blocks = array() ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
		);
	}
}
