<?php
/**
 * Opt-in migration from the legacy JSON meta schema to blocks.
 *
 * @package FForms
 */

namespace FForms\Migration;

use FForms\Post_Types;
use FForms\Schema;
use FForms\Schema\Schema_Repository;

final class Legacy_Migration {
	public static function boot(): void {
		add_action( 'admin_post_fforms_migrate_form', array( self::class, 'migrate' ) );
	}

	public static function can_migrate( int $form_id ): bool {
		return '' === trim( (string) get_post_field( 'post_content', $form_id ) ) && '' !== (string) get_post_meta( $form_id, '_fforms_schema', true );
	}

	public static function migrate(): void {
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		if ( ! $form_id || ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'fforms' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fforms_migrate_form_' . $form_id );
		if ( ! self::can_migrate( $form_id ) ) {
			wp_safe_redirect( get_edit_post_link( $form_id, 'url' ) );
			exit;
		}

		$schema  = Schema::normalize( (string) get_post_meta( $form_id, '_fforms_schema', true ) );
		$blocks  = array();
		foreach ( $schema['fields'] as $field ) {
			$attributes = array(
				'fieldId'     => $field['name'], 'name' => $field['name'], 'label' => $field['label'],
				'required'    => $field['required'], 'placeholder' => $field['placeholder'],
				'maxLength'   => $field['max_length'] ?? 0, 'options' => $field['options'] ?? array(),
			);
			$blocks[] = array( 'blockName' => 'fforms/field-' . $field['type'], 'attrs' => $attributes, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		}
		$blocks[] = array( 'blockName' => 'fforms/submit', 'attrs' => array( 'label' => __( 'Отправить', 'fforms' ) ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		$content  = serialize_blocks( array( array( 'blockName' => 'fforms/form', 'attrs' => array(), 'innerBlocks' => $blocks, 'innerHTML' => '', 'innerContent' => array_fill( 0, count( $blocks ), null ) ) ) );
		wp_update_post( array( 'ID' => $form_id, 'post_content' => $content ) );
		Schema_Repository::invalidate( $form_id );
		wp_safe_redirect( get_edit_post_link( $form_id, 'url' ) );
		exit;
	}
}
