<?php
/**
 * CSV export for saved entries.
 *
 * @package FForms
 */

namespace FForms;

final class Export {
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'admin_menu' ) );
		add_action( 'admin_post_fforms_export_csv', array( self::class, 'download' ) );
	}

	public static function admin_menu(): void {
		add_submenu_page( 'edit.php?post_type=' . Post_Types::FORM, __( 'Экспорт ответов', 'fforms' ), __( 'Экспорт CSV', 'fforms' ), 'manage_options', 'fforms-export', array( self::class, 'render_page' ) );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$forms = get_posts( array( 'post_type' => Post_Types::FORM, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Экспорт ответов', 'fforms' ); ?></h1>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="fforms_export_csv"><?php wp_nonce_field( 'fforms_export_csv' ); ?>
		<label for="fforms-export-form"><strong><?php esc_html_e( 'Форма', 'fforms' ); ?></strong></label>
		<select id="fforms-export-form" name="form_id"><option value="0"><?php esc_html_e( 'Все формы', 'fforms' ); ?></option><?php foreach ( $forms as $form ) : ?><option value="<?php echo esc_attr( $form->ID ); ?>"><?php echo esc_html( get_the_title( $form ) ); ?></option><?php endforeach; ?></select>
		<?php submit_button( __( 'Скачать CSV', 'fforms' ), 'primary', 'submit', false ); ?></form></div>
		<?php
	}

	public static function download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'fforms' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fforms_export_csv' );

		$form_id    = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$meta_query = $form_id ? array( array( 'key' => '_fforms_form_id', 'value' => $form_id, 'compare' => '=' ) ) : array();
		$entry_ids  = get_posts( array( 'post_type' => Post_Types::ENTRY, 'post_status' => 'private', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'ASC', 'fields' => 'ids', 'meta_query' => $meta_query ) );

		$rows       = array();
		$field_keys = array();
		foreach ( $entry_ids as $entry_id ) {
			$data       = json_decode( (string) get_post_meta( $entry_id, '_fforms_data', true ), true );
			$data       = is_array( $data ) ? $data : array();
			$field_keys = array_values( array_unique( array_merge( $field_keys, array_keys( $data ) ) ) );
			$rows[]     = array( 'id' => $entry_id, 'data' => $data );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="fforms-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Не удалось сформировать CSV.', 'fforms' ) );
		}
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array_merge( array( 'entry_id', 'form_id', 'form', 'status', 'submitted_at', 'source', 'ip', 'user_agent' ), $field_keys ), ',', '"', '' );
		foreach ( $rows as $row ) {
			$entry_id     = (int) $row['id'];
			$entry        = get_post( $entry_id );
			$entry_form_id = (int) get_post_meta( $entry_id, '_fforms_form_id', true );
			$csv_row      = array( $entry_id, $entry_form_id, get_the_title( $entry_form_id ), get_post_meta( $entry_id, '_fforms_status', true ), $entry ? $entry->post_date_gmt : '', get_post_meta( $entry_id, '_fforms_source', true ), get_post_meta( $entry_id, '_fforms_ip', true ), get_post_meta( $entry_id, '_fforms_user_agent', true ) );
			foreach ( $field_keys as $key ) {
				$csv_row[] = Post_Types::stringify( $row['data'][ $key ] ?? '' );
			}
			$csv_row = array_map( static fn( $cell ): string => self::safe_cell( (string) $cell ), $csv_row );
			fputcsv( $output, $csv_row, ',', '"', '' );
		}
		fclose( $output );
		exit;
	}

	private static function safe_cell( string $value ): string {
		return preg_match( '/^[\s\x00-\x1F]*[=+\-@]/u', $value ) ? "'" . $value : $value;
	}
}
