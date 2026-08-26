<?php
/**
 * Custom post types and their admin UI.
 *
 * @package FForms
 */

namespace FForms;

use WP_Post;

final class Post_Types {
	public const FORM  = 'fform';
	public const ENTRY = 'fform_entry';

	public static function boot(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::FORM, array( self::class, 'save_form' ) );
		add_action( 'save_post_' . self::ENTRY, array( self::class, 'save_entry' ) );
		add_filter( 'manage_' . self::ENTRY . '_posts_columns', array( self::class, 'entry_columns' ) );
		add_action( 'manage_' . self::ENTRY . '_posts_custom_column', array( self::class, 'render_entry_column' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type(
			self::FORM,
			array(
				'labels' => array(
					'name'          => __( 'Формы', 'fforms' ),
					'singular_name' => __( 'Форма', 'fforms' ),
					'add_new_item'  => __( 'Добавить форму', 'fforms' ),
					'edit_item'     => __( 'Редактировать форму', 'fforms' ),
					'menu_name'     => __( 'FForms', 'fforms' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_rest'        => true,
				'rest_base'           => 'fforms',
				'menu_icon'           => 'dashicons-feedback',
				'supports'            => array( 'title', 'custom-fields' ),
				'exclude_from_search' => true,
			)
		);

		register_post_type(
			self::ENTRY,
			array(
				'labels' => array(
					'name'          => __( 'Ответы', 'fforms' ),
					'singular_name' => __( 'Ответ', 'fforms' ),
					'edit_item'     => __( 'Просмотреть ответ', 'fforms' ),
					'menu_name'     => __( 'Ответы', 'fforms' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::FORM,
				'show_in_rest'        => false,
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
				'map_meta_cap'        => false,
				'capabilities'        => self::entry_capabilities(),
			)
		);

		self::register_meta();
	}

	private static function register_meta(): void {
		register_post_meta( self::FORM, '_fforms_type', array( 'type' => 'string', 'single' => true, 'default' => 'contact', 'show_in_rest' => true, 'sanitize_callback' => static fn( $value ): string => in_array( $value, array( 'contact', 'lead' ), true ) ? $value : 'contact' ) );
		register_post_meta( self::FORM, '_fforms_schema', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( Schema::class, 'sanitize_json' ) ) );

		foreach ( array( '_fforms_notification_to', '_fforms_notification_subject', '_fforms_success_message', '_fforms_autoreply_email_field', '_fforms_autoreply_subject', '_fforms_autoreply_message' ) as $key ) {
			register_post_meta( self::FORM, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => '_fforms_autoreply_message' === $key ? 'sanitize_textarea_field' : 'sanitize_text_field' ) );
		}
		register_post_meta( self::FORM, '_fforms_autoreply_enabled', array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => false ) );
		foreach ( array( '_fforms_form_id', '_fforms_created_post_id' ) as $key ) {
			register_post_meta( self::ENTRY, $key, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false ) );
		}
		foreach ( array( '_fforms_data', '_fforms_status', '_fforms_source', '_fforms_ip', '_fforms_user_agent' ) as $key ) {
			register_post_meta( self::ENTRY, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		}
	}

	public static function add_meta_boxes(): void {
		add_meta_box( 'fforms_form_settings', __( 'Настройки формы', 'fforms' ), array( self::class, 'render_form_meta_box' ), self::FORM, 'normal', 'high' );
		add_meta_box( 'fforms_entry_data', __( 'Данные ответа', 'fforms' ), array( self::class, 'render_entry_meta_box' ), self::ENTRY, 'normal', 'high' );
	}

	public static function render_form_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'fforms_save_form', 'fforms_form_nonce' );
		$type              = (string) get_post_meta( $post->ID, '_fforms_type', true );
		$schema            = (string) get_post_meta( $post->ID, '_fforms_schema', true ) ?: Schema::sanitize_json( Schema::defaults() );
		$notify_to         = (string) get_post_meta( $post->ID, '_fforms_notification_to', true );
		$notify_subject    = (string) get_post_meta( $post->ID, '_fforms_notification_subject', true );
		$success_message   = (string) get_post_meta( $post->ID, '_fforms_success_message', true );
		$autoreply_enabled = (bool) get_post_meta( $post->ID, '_fforms_autoreply_enabled', true );
		$email_field       = (string) get_post_meta( $post->ID, '_fforms_autoreply_email_field', true );
		$autoreply_subject = (string) get_post_meta( $post->ID, '_fforms_autoreply_subject', true );
		$autoreply_message = (string) get_post_meta( $post->ID, '_fforms_autoreply_message', true );
		?>
		<table class="form-table" role="presentation">
			<tr><th><label for="fforms_type"><?php esc_html_e( 'Тип формы', 'fforms' ); ?></label></th><td><select id="fforms_type" name="fforms_type"><option value="contact" <?php selected( $type, 'contact' ); ?>><?php esc_html_e( 'Контактная', 'fforms' ); ?></option><option value="lead" <?php selected( $type, 'lead' ); ?>><?php esc_html_e( 'Лид', 'fforms' ); ?></option></select></td></tr>
			<tr><th><label for="fforms_schema"><?php esc_html_e( 'JSON-схема', 'fforms' ); ?></label></th><td><textarea class="large-text code" rows="18" id="fforms_schema" name="fforms_schema"><?php echo esc_textarea( $schema ); ?></textarea><p class="description"><?php esc_html_e( 'Поля: name, label, type, required, placeholder, options, max_length. Типы: text, textarea, email, tel, url, number, select, radio, checkbox, hidden.', 'fforms' ); ?></p></td></tr>
			<tr><th><label for="fforms_notification_to"><?php esc_html_e( 'Получатели', 'fforms' ); ?></label></th><td><input class="regular-text" type="text" id="fforms_notification_to" name="fforms_notification_to" value="<?php echo esc_attr( $notify_to ); ?>"><p class="description"><?php esc_html_e( 'Email через запятую; если пусто — email администратора.', 'fforms' ); ?></p></td></tr>
			<tr><th><label for="fforms_notification_subject"><?php esc_html_e( 'Тема уведомления', 'fforms' ); ?></label></th><td><input class="regular-text" type="text" id="fforms_notification_subject" name="fforms_notification_subject" value="<?php echo esc_attr( $notify_subject ); ?>"></td></tr>
			<tr><th><label for="fforms_success_message"><?php esc_html_e( 'Сообщение об успехе', 'fforms' ); ?></label></th><td><input class="large-text" type="text" id="fforms_success_message" name="fforms_success_message" value="<?php echo esc_attr( $success_message ); ?>" placeholder="<?php esc_attr_e( 'Спасибо! Форма отправлена.', 'fforms' ); ?>"></td></tr>
		</table>
		<h3><?php esc_html_e( 'Автоответ', 'fforms' ); ?></h3>
		<p><label><input type="checkbox" name="fforms_autoreply_enabled" value="1" <?php checked( $autoreply_enabled ); ?>> <?php esc_html_e( 'Отправлять автоответ пользователю', 'fforms' ); ?></label></p>
		<p><label for="fforms_autoreply_email_field"><?php esc_html_e( 'Имя email-поля', 'fforms' ); ?></label><br><input class="regular-text" type="text" id="fforms_autoreply_email_field" name="fforms_autoreply_email_field" value="<?php echo esc_attr( $email_field ?: 'email' ); ?>"></p>
		<p><label for="fforms_autoreply_subject"><?php esc_html_e( 'Тема автоответа', 'fforms' ); ?></label><br><input class="large-text" type="text" id="fforms_autoreply_subject" name="fforms_autoreply_subject" value="<?php echo esc_attr( $autoreply_subject ); ?>"></p>
		<p><label for="fforms_autoreply_message"><?php esc_html_e( 'Текст автоответа', 'fforms' ); ?></label><br><textarea class="large-text" rows="5" id="fforms_autoreply_message" name="fforms_autoreply_message"><?php echo esc_textarea( $autoreply_message ); ?></textarea></p>
		<?php
	}

	public static function save_form( int $post_id ): void {
		if ( ! self::can_save( $post_id, 'fforms_form_nonce', 'fforms_save_form' ) ) {
			return;
		}
		$type = isset( $_POST['fforms_type'] ) ? sanitize_key( wp_unslash( $_POST['fforms_type'] ) ) : 'contact';
		update_post_meta( $post_id, '_fforms_type', in_array( $type, array( 'contact', 'lead' ), true ) ? $type : 'contact' );
		if ( isset( $_POST['fforms_schema'] ) ) {
			update_post_meta( $post_id, '_fforms_schema', Schema::sanitize_json( wp_unslash( $_POST['fforms_schema'] ) ) );
		}
		$fields = array(
			'fforms_notification_to'       => '_fforms_notification_to',
			'fforms_notification_subject'  => '_fforms_notification_subject',
			'fforms_success_message'        => '_fforms_success_message',
			'fforms_autoreply_email_field' => '_fforms_autoreply_email_field',
			'fforms_autoreply_subject'     => '_fforms_autoreply_subject',
		);
		foreach ( $fields as $request_key => $meta_key ) {
			$value = isset( $_POST[ $request_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $request_key ] ) ) : '';
			update_post_meta( $post_id, $meta_key, $value );
		}
		$message = isset( $_POST['fforms_autoreply_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fforms_autoreply_message'] ) ) : '';
		update_post_meta( $post_id, '_fforms_autoreply_message', $message );
		update_post_meta( $post_id, '_fforms_autoreply_enabled', isset( $_POST['fforms_autoreply_enabled'] ) ? '1' : '0' );
	}

	public static function render_entry_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'fforms_save_entry', 'fforms_entry_nonce' );
		$data   = json_decode( (string) get_post_meta( $post->ID, '_fforms_data', true ), true );
		$status = (string) get_post_meta( $post->ID, '_fforms_status', true );
		?>
		<p><label for="fforms_entry_status"><strong><?php esc_html_e( 'Статус', 'fforms' ); ?></strong></label><br><select id="fforms_entry_status" name="fforms_entry_status"><?php foreach ( array( 'new' => __( 'Новый', 'fforms' ), 'read' => __( 'Прочитан', 'fforms' ), 'replied' => __( 'Отвечен', 'fforms' ), 'spam' => __( 'Спам', 'fforms' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status ?: 'new', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
		<table class="widefat striped"><tbody><?php foreach ( is_array( $data ) ? $data : array() as $key => $value ) : ?><tr><th style="width:25%"><?php echo esc_html( (string) $key ); ?></th><td><?php echo nl2br( esc_html( self::stringify( $value ) ) ); ?></td></tr><?php endforeach; ?></tbody></table>
		<p><small><?php echo esc_html( sprintf( 'IP: %s · User-Agent: %s · Source: %s', get_post_meta( $post->ID, '_fforms_ip', true ), get_post_meta( $post->ID, '_fforms_user_agent', true ), get_post_meta( $post->ID, '_fforms_source', true ) ) ); ?></small></p>
		<?php
	}

	public static function save_entry( int $post_id ): void {
		if ( ! self::can_save( $post_id, 'fforms_entry_nonce', 'fforms_save_entry' ) ) {
			return;
		}
		$status = isset( $_POST['fforms_entry_status'] ) ? sanitize_key( wp_unslash( $_POST['fforms_entry_status'] ) ) : 'new';
		update_post_meta( $post_id, '_fforms_status', in_array( $status, array( 'new', 'read', 'replied', 'spam' ), true ) ? $status : 'new' );
	}

	public static function entry_columns( array $columns ): array {
		return array( 'cb' => $columns['cb'] ?? '<input type="checkbox" />', 'title' => __( 'Ответ', 'fforms' ), 'fforms_form' => __( 'Форма', 'fforms' ), 'fforms_status' => __( 'Статус', 'fforms' ), 'fforms_preview' => __( 'Данные', 'fforms' ), 'date' => $columns['date'] ?? __( 'Дата', 'fforms' ) );
	}

	public static function render_entry_column( string $column, int $post_id ): void {
		if ( 'fforms_form' === $column ) {
			$form_id = (int) get_post_meta( $post_id, '_fforms_form_id', true );
			echo $form_id ? '<a href="' . esc_url( get_edit_post_link( $form_id ) ) . '">' . esc_html( get_the_title( $form_id ) ) . '</a>' : '—';
		} elseif ( 'fforms_status' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_fforms_status', true ) );
		} elseif ( 'fforms_preview' === $column ) {
			$data = json_decode( (string) get_post_meta( $post_id, '_fforms_data', true ), true );
			echo esc_html( self::stringify( is_array( $data ) ? array_slice( $data, 0, 3, true ) : array() ) );
		}
	}

	public static function stringify( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Да', 'fforms' ) : __( 'Нет', 'fforms' );
		}
		return is_array( $value ) ? implode( ', ', array_map( array( self::class, 'stringify' ), $value ) ) : (string) $value;
	}

	private static function can_save( int $post_id, string $nonce_key, string $action ): bool {
		return ! wp_is_post_autosave( $post_id ) && ! wp_is_post_revision( $post_id ) && isset( $_POST[ $nonce_key ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), $action ) && current_user_can( 'edit_post', $post_id );
	}

	private static function entry_capabilities(): array {
		return array(
			'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options',
			'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
			'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options',
			'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options',
			'edit_published_posts' => 'manage_options', 'create_posts' => 'do_not_allow',
		);
	}
}
