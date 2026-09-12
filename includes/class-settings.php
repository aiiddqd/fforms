<?php
/**
 * SMTP settings and PHPMailer integration.
 *
 * @package FForms
 */

namespace FForms;

final class Settings {
	public const OPTION = 'fforms_smtp';

	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'admin_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'phpmailer_init', array( self::class, 'configure_mailer' ), 20 );
		// The built-in main form reads its origins and recipients from here.
		add_action( 'update_option_' . self::OPTION, array( Registry\Main_Form::class, 'flush' ) );
	}

	public static function admin_menu(): void {
		add_submenu_page( 'fforms', __( 'Настройки FForms', 'fforms' ), __( 'Настройки', 'fforms' ), 'manage_options', 'fforms-settings', array( self::class, 'render_page' ) );
	}

	public static function register_settings(): void {
		register_setting( 'fforms_settings', self::OPTION, array( 'type' => 'object', 'sanitize_callback' => array( self::class, 'sanitize' ), 'default' => array() ) );
	}

	public static function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$current  = self::get();
		$security = sanitize_key( (string) ( $input['encryption'] ?? '' ) );
		$password = isset( $input['password'] ) ? (string) $input['password'] : '';
		if ( ! empty( $input['clear_password'] ) ) {
			$password = '';
		} elseif ( '' === $password ) {
			$password = (string) ( $current['password'] ?? '' );
		}

		return array(
			'enabled'       => ! empty( $input['enabled'] ),
			'host'          => sanitize_text_field( (string) ( $input['host'] ?? '' ) ),
			'port'          => min( 65535, max( 1, absint( $input['port'] ?? 587 ) ) ),
			'encryption'    => in_array( $security, array( '', 'tls', 'ssl' ), true ) ? $security : 'tls',
			'auth'          => ! empty( $input['auth'] ),
			'username'      => sanitize_text_field( (string) ( $input['username'] ?? '' ) ),
			'password'      => $password,
			'from_email'    => sanitize_email( (string) ( $input['from_email'] ?? '' ) ),
			'from_name'     => sanitize_text_field( (string) ( $input['from_name'] ?? '' ) ),
			'notifications' => ! empty( $input['notifications'] ),

			'main_form_origins'         => self::sanitize_origins( $input['main_form_origins'] ?? '' ),
			'main_form_notifications'   => ! empty( $input['main_form_notifications'] ),
			'main_form_notification_to' => sanitize_text_field( (string) ( $input['main_form_notification_to'] ?? '' ) ),
			'form_types_strict'         => ! empty( $input['form_types_strict'] ),
		);
	}

	/**
	 * Accept a comma- or newline-separated origin list and keep only absolute
	 * http(s) origins without a trailing slash, so CORS can match them exactly.
	 *
	 * @return array<int, string>
	 */
	public static function sanitize_origins( mixed $input ): array {
		$parts = is_array( $input ) ? $input : preg_split( '/[\s,]+/', (string) $input );
		$clean = array();
		foreach ( (array) $parts as $part ) {
			$url = esc_url_raw( trim( (string) $part ), array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$clean[] = untrailingslashit( $url );
			}
		}
		return array_values( array_unique( $clean ) );
	}

	public static function get(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'enabled' => false, 'host' => '', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'username' => '', 'password' => '', 'from_email' => '', 'from_name' => get_bloginfo( 'name' ), 'notifications' => false,
				'main_form_origins' => array(), 'main_form_notifications' => false, 'main_form_notification_to' => '', 'form_types_strict' => false,
			)
		);
	}

	public static function configure_mailer( object $phpmailer ): void {
		$settings = self::get();
		if ( empty( $settings['enabled'] ) || '' === $settings['host'] || ! method_exists( $phpmailer, 'isSMTP' ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['host'];
		$phpmailer->Port       = (int) $settings['port'];
		$phpmailer->SMTPAuth   = (bool) $settings['auth'];
		$phpmailer->Username   = $settings['username'];
		$phpmailer->Password   = $settings['password'];
		$phpmailer->SMTPSecure = $settings['encryption'];
		if ( is_email( $settings['from_email'] ) ) {
			$phpmailer->setFrom( $settings['from_email'], $settings['from_name'], false );
		}
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::get();
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Настройки FForms', 'fforms' ); ?></h1>
		<p><?php esc_html_e( 'Встроенный SMTP включайте только в том случае, если отправкой почты не управляет другой SMTP-плагин.', 'fforms' ); ?></p>
		<form action="options.php" method="post"><?php settings_fields( 'fforms_settings' ); ?>
		<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Уведомления', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[notifications]" value="1" <?php checked( $s['notifications'] ); ?>> <?php esc_html_e( 'Включить настройки уведомлений и автоответов для форм', 'fforms' ); ?></label><p class="description"><?php esc_html_e( 'После включения настройте отправку уведомлений и автоответы отдельно для каждой формы.', 'fforms' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Разрешённые origins главной формы', 'fforms' ); ?></th><td><textarea class="large-text code" rows="3" id="fforms-main-form-origins" name="<?php echo esc_attr( self::OPTION ); ?>[main_form_origins]" placeholder="https://example.com, https://app.example.com"><?php echo esc_textarea( implode( ", ", (array) $s['main_form_origins'] ) ); ?></textarea><p class="description"><?php esc_html_e( 'Домены через запятую, которым разрешён кросс-доменный запрос к POST /fforms/v1/main. Пусто — CORS-заголовки не отправляются.', 'fforms' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Уведомления главной формы', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[main_form_notifications]" value="1" <?php checked( $s['main_form_notifications'] ); ?>> <?php esc_html_e( 'Отправлять письмо о новой заявке главной формы', 'fforms' ); ?></label><p><input class="regular-text" type="text" id="fforms-main-form-notification-to" name="<?php echo esc_attr( self::OPTION ); ?>[main_form_notification_to]" value="<?php echo esc_attr( (string) $s['main_form_notification_to'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( "admin_email" ) ); ?>"></p><p class="description"><?php esc_html_e( 'Получатели через запятую. Пусто — письмо уходит на адрес администратора.', 'fforms' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Типы заявок', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[form_types_strict]" value="1" <?php checked( $s['form_types_strict'] ); ?>> <?php esc_html_e( 'Принимать только существующие типы', 'fforms' ); ?></label><p class="description"><?php esc_html_e( 'При включении неизвестный formType возвращает 422 и заявка не сохраняется. По умолчанию новый тип создаётся автоматически.', 'fforms' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'SMTP', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Использовать SMTP FForms', 'fforms' ); ?></label></td></tr>
		<?php self::input_row( 'host', __( 'SMTP host', 'fforms' ), $s['host'] ); ?>
		<?php self::input_row( 'port', __( 'Порт', 'fforms' ), (string) $s['port'], 'number' ); ?>
		<tr><th><label for="fforms-encryption"><?php esc_html_e( 'Шифрование', 'fforms' ); ?></label></th><td><select id="fforms-encryption" name="<?php echo esc_attr( self::OPTION ); ?>[encryption]"><option value="" <?php selected( $s['encryption'], '' ); ?>><?php esc_html_e( 'Нет', 'fforms' ); ?></option><option value="tls" <?php selected( $s['encryption'], 'tls' ); ?>>TLS</option><option value="ssl" <?php selected( $s['encryption'], 'ssl' ); ?>>SSL</option></select></td></tr>
		<tr><th><?php esc_html_e( 'Авторизация', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auth]" value="1" <?php checked( $s['auth'] ); ?>> <?php esc_html_e( 'SMTP требует логин и пароль', 'fforms' ); ?></label></td></tr>
		<?php self::input_row( 'username', __( 'Логин', 'fforms' ), $s['username'] ); ?>
		<?php self::input_row( 'password', __( 'Пароль', 'fforms' ), '', 'password' ); ?>
		<tr><th></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[clear_password]" value="1"> <?php esc_html_e( 'Удалить сохранённый пароль', 'fforms' ); ?></label></td></tr>
		<?php self::input_row( 'from_email', __( 'Email отправителя', 'fforms' ), $s['from_email'], 'email' ); ?>
		<?php self::input_row( 'from_name', __( 'Имя отправителя', 'fforms' ), $s['from_name'] ); ?>
		</table><?php submit_button(); ?></form></div>
		<?php
	}

	private static function input_row( string $key, string $label, string $value, string $type = 'text' ): void {
		?>
		<tr><th><label for="fforms-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" autocomplete="off" type="<?php echo esc_attr( $type ); ?>" id="fforms-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>"></td></tr>
		<?php
	}
}
