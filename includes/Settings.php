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
		add_submenu_page( 'fforms', __( 'FForms settings', 'fforms' ), __( 'Settings', 'fforms' ), 'manage_options', 'fforms-settings', array( self::class, 'render_page' ) );
	}

	public static function register_settings(): void {
		register_setting( 'fforms_settings', self::OPTION, array( 'type' => 'object', 'sanitize_callback' => array( self::class, 'sanitize' ), 'default' => array() ) );
	}

	public static function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$current  = self::get();
		$stored   = (array) get_option( self::OPTION, array() );
		$tab      = sanitize_key( (string) ( $input['settings_tab'] ?? 'general' ) );
		if ( 'smtp' === $tab ) {
			// Saving SMTP must not turn off unrelated notification settings simply
			// because the General tab did not submit their fields.
			$input = array_merge(
				array(
					'notifications'                    => $current['notifications'],
					'default_notification_recipients' => $current['default_notification_recipients'],
					'main_form_origins'                => $current['main_form_origins'],
					'main_form_notifications'          => $current['main_form_notifications'],
					'form_types_strict'                => $current['form_types_strict'],
				),
				$input
			);
		} else {
			// Likewise, General must retain credentials and mailer settings that are
			// deliberately only present on the SMTP tab.
			$input = array_merge(
				array(
					'enabled'    => $current['enabled'],
					'host'       => $current['host'],
					'port'       => $current['port'],
					'encryption' => $current['encryption'],
					'auth'       => $current['auth'],
					'username'   => $current['username'],
					'password'   => $current['password'],
					'from_email' => $current['from_email'],
					'from_name'  => $current['from_name'],
				),
				$input
			);
		}
		$security = sanitize_key( (string) ( $input['encryption'] ?? '' ) );
		$password = isset( $input['password'] ) ? (string) $input['password'] : '';
		if ( ! empty( $input['clear_password'] ) ) {
			$password = '';
		} elseif ( '' === $password ) {
			$password = (string) ( $current['password'] ?? '' );
		}
		$recipients = sanitize_textarea_field( (string) ( $input['default_notification_recipients'] ?? '' ) );
		// Move the old main-only address exactly once when this option is next
		// saved. Do not bring it back if an administrator later clears the new
		// common field deliberately.
		if ( '' === trim( $recipients ) && empty( $stored['default_notification_recipients'] ) && ! empty( $stored['main_form_notification_to'] ) ) {
			$recipients = sanitize_textarea_field( (string) $stored['main_form_notification_to'] );
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
			'default_notification_recipients' => $recipients,

			'main_form_origins'         => self::sanitize_origins( $input['main_form_origins'] ?? '' ),
			'main_form_notifications'   => ! empty( $input['main_form_notifications'] ),
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
				'enabled' => false, 'host' => '', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'username' => '', 'password' => '', 'from_email' => '', 'from_name' => get_bloginfo( 'name' ), 'notifications' => false, 'default_notification_recipients' => '',
				'main_form_origins' => array(), 'main_form_notifications' => false, 'form_types_strict' => false,
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
		$s        = self::get();
		$tab      = 'smtp' === sanitize_key( (string) ( $_GET['tab'] ?? 'general' ) ) ? 'smtp' : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_url = admin_url( 'admin.php?page=fforms-settings' );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'FForms settings', 'fforms' ); ?></h1>
		<h2 class="nav-tab-wrapper"><a href="<?php echo esc_url( $page_url ); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'fforms' ); ?></a><a href="<?php echo esc_url( add_query_arg( 'tab', 'smtp', $page_url ) ); ?>" class="nav-tab <?php echo 'smtp' === $tab ? 'nav-tab-active' : ''; ?>">SMTP</a></h2>
		<form action="options.php" method="post"><?php settings_fields( 'fforms_settings' ); ?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[settings_tab]" value="<?php echo esc_attr( $tab ); ?>">
		<table class="form-table" role="presentation">
		<?php if ( 'general' === $tab ) : ?>
		<tr><th><?php esc_html_e( 'Notifications', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[notifications]" value="1" <?php checked( $s['notifications'] ); ?>> <?php esc_html_e( 'Enable notification and auto-reply settings for forms', 'fforms' ); ?></label><p class="description"><?php esc_html_e( 'Once enabled, configure notifications and auto-replies separately for each form.', 'fforms' ); ?></p><p><label for="fforms-default-notification-recipients"><strong><?php esc_html_e( 'Default notification recipients', 'fforms' ); ?></strong></label><br><textarea class="large-text" rows="3" id="fforms-default-notification-recipients" name="<?php echo esc_attr( self::OPTION ); ?>[default_notification_recipients]" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"><?php echo esc_textarea( (string) $s['default_notification_recipients'] ); ?></textarea></p><p class="description"><?php esc_html_e( 'Comma- or newline-separated emails. Empty — the current administrator email is used.', 'fforms' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Allowed origins of the main form', 'fforms' ); ?></th><td><textarea class="large-text code" rows="3" id="fforms-main-form-origins" name="<?php echo esc_attr( self::OPTION ); ?>[main_form_origins]" placeholder="https://example.com, https://app.example.com"><?php echo esc_textarea( implode( ", ", (array) $s['main_form_origins'] ) ); ?></textarea><p class="description"><?php esc_html_e( 'Comma-separated domains allowed to make a cross-origin request to POST /fforms/v1/main. Empty — no CORS headers are sent.', 'fforms' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Main form notifications', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[main_form_notifications]" value="1" <?php checked( $s['main_form_notifications'] ); ?>> <?php esc_html_e( 'Send an email about a new main form submission', 'fforms' ); ?></label><p class="description"><?php esc_html_e( 'Uses the default notification recipients above.', 'fforms' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Form types', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[form_types_strict]" value="1" <?php checked( $s['form_types_strict'] ); ?>> <?php esc_html_e( 'Accept only existing types', 'fforms' ); ?></label><p class="description"><?php esc_html_e( 'When enabled, an unknown formType returns 422 and the submission is not saved. By default a new type is created automatically.', 'fforms' ); ?></p></td></tr>
		<?php else : ?>
		<tr><td colspan="2"><p><?php esc_html_e( 'Enable the built-in SMTP only when no other SMTP plugin handles email delivery.', 'fforms' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'SMTP', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Use the FForms SMTP', 'fforms' ); ?></label></td></tr>
		<?php self::input_row( 'host', __( 'SMTP host', 'fforms' ), $s['host'] ); ?>
		<?php self::input_row( 'port', __( 'Port', 'fforms' ), (string) $s['port'], 'number' ); ?>
		<tr><th><label for="fforms-encryption"><?php esc_html_e( 'Encryption', 'fforms' ); ?></label></th><td><select id="fforms-encryption" name="<?php echo esc_attr( self::OPTION ); ?>[encryption]"><option value="" <?php selected( $s['encryption'], '' ); ?>><?php esc_html_e( 'None', 'fforms' ); ?></option><option value="tls" <?php selected( $s['encryption'], 'tls' ); ?>>TLS</option><option value="ssl" <?php selected( $s['encryption'], 'ssl' ); ?>>SSL</option></select></td></tr>
		<tr><th><?php esc_html_e( 'Authentication', 'fforms' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auth]" value="1" <?php checked( $s['auth'] ); ?>> <?php esc_html_e( 'SMTP requires a username and password', 'fforms' ); ?></label></td></tr>
		<?php self::input_row( 'username', __( 'Username', 'fforms' ), $s['username'] ); ?>
		<?php self::input_row( 'password', __( 'Password', 'fforms' ), '', 'password' ); ?>
		<tr><th></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[clear_password]" value="1"> <?php esc_html_e( 'Delete the saved password', 'fforms' ); ?></label></td></tr>
		<?php self::input_row( 'from_email', __( 'From email', 'fforms' ), $s['from_email'], 'email' ); ?>
		<?php self::input_row( 'from_name', __( 'From name', 'fforms' ), $s['from_name'] ); ?>
		<?php endif; ?>
		</table><?php submit_button(); ?></form></div>
		<?php
	}

	private static function input_row( string $key, string $label, string $value, string $type = 'text' ): void {
		?>
		<tr><th><label for="fforms-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" autocomplete="off" type="<?php echo esc_attr( $type ); ?>" id="fforms-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>"></td></tr>
		<?php
	}
}
