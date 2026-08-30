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
	}

	public static function admin_menu(): void {
		add_submenu_page( 'edit.php?post_type=' . Post_Types::FORM, __( 'Настройки FForms', 'fforms' ), __( 'Настройки', 'fforms' ), 'manage_options', 'fforms-settings', array( self::class, 'render_page' ) );
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
		);
	}

	public static function get(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array( 'enabled' => false, 'host' => '', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'username' => '', 'password' => '', 'from_email' => '', 'from_name' => get_bloginfo( 'name' ), 'notifications' => false )
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
