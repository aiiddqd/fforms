<?php
/**
 * Plugin overview page: quickstart, forms preview, headless API example, recent submissions.
 *
 * @package FForms
 */

namespace FForms;

final class Dashboard {
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'admin_menu' ) );
	}

	public static function admin_menu(): void {
		add_menu_page(
			__( 'FForms', 'fforms' ),
			__( 'FForms', 'fforms' ),
			'edit_posts',
			'fforms',
			array( self::class, 'render_page' ),
			'dashicons-feedback'
		);

		// Position 0 keeps "Обзор" first, ahead of the CPT-generated "Формы"/"Добавить форму" items
		// which WordPress appends to $submenu['fforms'] before the admin_menu hook runs.
		add_submenu_page(
			'fforms',
			__( 'Обзор', 'fforms' ),
			__( 'Обзор', 'fforms' ),
			'edit_posts',
			'fforms',
			array( self::class, 'render_page' ),
			0
		);
	}

	private const REPO_URL = 'https://github.com/aiiddqd/fforms';

	public static function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post_counts = wp_count_posts( Post_Types::FORM );
		$forms_count = (int) ( $post_counts->publish ?? 0 ) + (int) ( $post_counts->draft ?? 0 ) + (int) ( $post_counts->private ?? 0 );
		$code_forms  = Registry\Code_Forms::all();
		$smtp        = Settings::get();
		?>
		<div class="wrap fforms-dashboard">
			<style>
				.fforms-dashboard .fforms-hero { margin: 20px 0 28px; }
				.fforms-dashboard .fforms-hero p { max-width: 640px; font-size: 14px; }
				.fforms-dashboard .fforms-cards { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 32px; }
				.fforms-dashboard .fforms-card { flex: 1 1 240px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 16px 20px; }
				.fforms-dashboard .fforms-card h2 { margin-top: 0; font-size: 14px; text-transform: uppercase; color: #646970; }
				.fforms-dashboard .fforms-card .fforms-card-status { font-size: 20px; font-weight: 600; margin: 4px 0; }
				.fforms-dashboard .fforms-card .fforms-card-status.is-on { color: #007017; }
				.fforms-dashboard .fforms-card .fforms-card-status.is-off { color: #8a8a8a; }
				.fforms-dashboard .fforms-faq { max-width: 720px; }
				.fforms-dashboard .fforms-faq details { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 16px; margin-bottom: 8px; }
				.fforms-dashboard .fforms-faq summary { cursor: pointer; font-weight: 600; }
				.fforms-dashboard .fforms-faq pre { background: #f6f7f7; border: 1px solid #dcdcde; padding: 12px 16px; overflow: auto; }
			</style>

			<div class="fforms-hero">
				<h1><?php esc_html_e( 'FForms', 'fforms' ); ?></h1>
				<p><?php esc_html_e( 'Лёгкий, headless-friendly приём форм: собирайте данные из блоков Gutenberg или из внешних сайтов через REST API.', 'fforms' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Types::FORM ) ); ?>" class="button button-primary"><?php esc_html_e( 'Добавить форму', 'fforms' ); ?></a>
					<a href="<?php echo esc_url( self::REPO_URL ); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Читать документацию', 'fforms' ); ?></a>
				</p>
			</div>

			<div class="fforms-cards">
				<div class="fforms-card">
					<h2><?php esc_html_e( 'Формы', 'fforms' ); ?></h2>
					<p class="fforms-card-status"><?php echo esc_html( sprintf( _n( '%d форма', '%d форм', $forms_count, 'fforms' ), $forms_count ) ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::FORM ) ); ?>"><?php esc_html_e( 'Все формы →', 'fforms' ); ?></a></p>
				</div>
				<div class="fforms-card">
					<h2><?php esc_html_e( 'SMTP', 'fforms' ); ?></h2>
					<?php if ( $smtp['enabled'] ) : ?>
						<p class="fforms-card-status is-on"><?php esc_html_e( 'Включён', 'fforms' ); ?></p>
						<p><?php echo esc_html( $smtp['host'] ?: __( 'Хост не указан', 'fforms' ) ); ?></p>
					<?php else : ?>
						<p class="fforms-card-status is-off"><?php esc_html_e( 'Выключен', 'fforms' ); ?></p>
						<p><?php esc_html_e( 'Письма уходят через стандартный wp_mail()', 'fforms' ); ?></p>
					<?php endif; ?>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fforms-settings' ) ); ?>"><?php esc_html_e( 'Настройки →', 'fforms' ); ?></a></p>
				</div>
				<div class="fforms-card">
					<h2><?php esc_html_e( 'Headless-режим', 'fforms' ); ?></h2>
					<?php if ( array() === $code_forms ) : ?>
						<p class="fforms-card-status is-off"><?php esc_html_e( 'Не используется', 'fforms' ); ?></p>
					<?php else : ?>
						<p class="fforms-card-status is-on"><?php echo esc_html( sprintf( _n( '%d форма из кода', '%d форм из кода', count( $code_forms ), 'fforms' ), count( $code_forms ) ) ); ?></p>
					<?php endif; ?>
					<p><a href="#fforms-faq-headless"><?php esc_html_e( 'Как подключить →', 'fforms' ); ?></a></p>
				</div>
			</div>

			<h2><?php esc_html_e( 'Частые вопросы', 'fforms' ); ?></h2>
			<div class="fforms-faq">
				<details>
					<summary><?php esc_html_e( 'Как быстро создать форму?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'Нажмите «Добавить форму», соберите поля блоками FForms прямо в редакторе Gutenberg и опубликуйте запись — форма сразу становится доступна на сайте и через REST API.', 'fforms' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Как настроить отправку писем?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'В разделе «Настройки» включите встроенный SMTP и укажите хост, порт и логин — либо оставьте выключенным, если почтой уже управляет другой SMTP-плагин. Уведомления и автоответы для конкретной формы настраиваются в самой форме.', 'fforms' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Как выгрузить заявки?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'В разделе «Экспорт CSV» выберите форму (или все сразу) и нажмите «Скачать CSV».', 'fforms' ); ?></p>
				</details>
				<details id="fforms-faq-headless">
					<summary><?php esc_html_e( 'Как добавить headless-форму (форму из кода)?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'Зарегистрируйте форму на хуке fforms_register_forms — она станет доступна через REST API по своему ключу, без создания записи в Gutenberg.', 'fforms' ); ?></p>
					<pre><code>add_action( 'fforms_register_forms', function () {
	fforms_add_api_route( 'contact_astro', array(
		'title'   =&gt; 'Контакт (Astro)',
		'fields'  =&gt; array(
			array( 'name' =&gt; 'email', 'label' =&gt; 'Email', 'type' =&gt; 'email', 'required' =&gt; true ),
			array( 'name' =&gt; 'message', 'label' =&gt; 'Сообщение', 'type' =&gt; 'textarea', 'required' =&gt; true ),
		),
		'origins' =&gt; array( 'https://example.com' ),
	) );
} );</code></pre>
					<p><?php esc_html_e( 'Отправка данных формы с внешнего сайта:', 'fforms' ); ?></p>
					<pre><code>curl -X POST <?php echo esc_html( rest_url( 'fforms/v1/submit' ) ); ?> \
	-H "Content-Type: application/json" \
	-d '{
		"form_key": "contact_astro",
		"fields": { "email": "user@example.com", "message": "Hello!" }
	}'</code></pre>
				</details>
			</div>
		</div>
		<?php
	}
}
