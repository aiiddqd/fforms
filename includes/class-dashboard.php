<?php
/**
 * Plugin overview page: quickstart, forms preview, headless API example, recent submissions.
 *
 * @package FForms
 */

namespace FForms;

final class Dashboard {
	public const PAGE = 'fforms-dashboard';

	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'admin_menu' ) );
		add_action( 'admin_menu', array( self::class, 'drop_duplicate_submenu' ), 100 );
		add_action( 'load-toplevel_page_fforms', array( self::class, 'redirect_legacy_page' ) );
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
			self::PAGE,
			array( self::class, 'render_page' ),
			0
		);
	}

	/**
	 * The top-level slug stays `fforms` — both CPTs use it as show_in_menu and
	 * the settings/export pages as their parent. Only the overview page moved,
	 * so the auto-generated duplicate entry goes away.
	 */
	public static function drop_duplicate_submenu(): void {
		remove_submenu_page( 'fforms', 'fforms' );
	}

	/** Keep old bookmarks and documentation links working. */
	public static function redirect_legacy_page(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	private const REPO_URL = 'https://github.com/aiiddqd/fforms';

	/**
	 * Copy-ready requests built from the live endpoint URL, so a frontend
	 * developer pastes working values instead of reading documentation.
	 *
	 * @return array<int, array{title: string, description: string, code: string}>
	 */
	private static function api_examples( string $main_url ): array {
		$curl = static fn( string $body ): string => sprintf( "curl -X POST %s \\\n  -H \"Content-Type: application/json\" \\\n  -d '%s'", $main_url, $body );

		return array(
			array(
				'title'       => __( 'Контактная форма', 'fforms' ),
				'description' => __( 'Без formType — заявка попадает в главную форму.', 'fforms' ),
				'code'        => $curl( "{\n    \"name\": \"Иван\",\n    \"email\": \"ivan@example.com\",\n    \"message\": \"Здравствуйте!\"\n  }" ),
			),
			array(
				'title'       => __( 'Заявка на консультацию', 'fforms' ),
				'description' => __( 'formType заводит тип формы: переименуйте термин в админке, slug останется прежним.', 'fforms' ),
				'code'        => $curl( "{\n    \"formType\": \"consultation_request\",\n    \"name\": \"Иван\",\n    \"phone\": \"+7 900 000-00-00\",\n    \"email\": \"ivan@example.com\"\n  }" ),
			),
			array(
				'title'       => __( 'Email и сайт компании', 'fforms' ),
				'description' => __( 'Поле website не входит в схему и сохраняется в customFields — настраивать ничего не нужно.', 'fforms' ),
				'code'        => $curl( "{\n    \"email\": \"sales@acme.dev\",\n    \"website\": \"https://acme.dev\"\n  }" ),
			),
			array(
				'title'       => __( 'Заявка с UTM-меткой', 'fforms' ),
				'description' => __( 'ref и meta сохраняют контекст запроса отдельно от проверенных полей схемы.', 'fforms' ),
				'code'        => $curl( "{\n    \"message\": \"Перезвоните\",\n    \"phone\": \"+7 900 000-00-00\",\n    \"ref\": \"yandex-direct\",\n    \"meta\": { \"page\": \"/pricing\", \"locale\": \"ru\" }\n  }" ),
			),
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post_counts    = wp_count_posts( Post_Types::FORM );
		$forms_count    = (int) ( $post_counts->publish ?? 0 ) + (int) ( $post_counts->draft ?? 0 ) + (int) ( $post_counts->private ?? 0 );
		$code_forms     = Registry\Code_Forms::all();
		$smtp           = Settings::get();
		$can_view_entries = current_user_can( 'manage_options' );
		$entries_count    = $can_view_entries ? (int) ( wp_count_posts( Post_Types::ENTRY )->private ?? 0 ) : 0;
		$main_url         = rest_url( 'fforms/v1/main' );
		$origins          = (array) $smtp['main_form_origins'];
		$strict_types     = ! empty( $smtp['form_types_strict'] );
		$types            = get_terms( array( 'taxonomy' => Form_Types::TAXONOMY, 'hide_empty' => false, 'orderby' => 'name' ) );
		$types            = is_array( $types ) ? $types : array();
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
				.fforms-dashboard .fforms-faq dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0 0 16px; }
				.fforms-dashboard .fforms-faq dt { color: #646970; }
				.fforms-dashboard .fforms-faq dd { margin: 0; }
				.fforms-dashboard .fforms-faq .fforms-example { border-top: 1px solid #f0f0f1; padding-top: 12px; margin-top: 12px; }
				.fforms-dashboard .fforms-faq .fforms-example-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
				.fforms-dashboard .fforms-faq h3 { margin: 0; font-size: 13px; }
				.fforms-dashboard .fforms-faq .fforms-example pre { margin: 8px 0 0; }
			</style>

			<div class="fforms-hero">
				<h1><?php esc_html_e( 'FForms', 'fforms' ); ?></h1>
				<p><?php esc_html_e( 'Лёгкий, headless-friendly приём форм: собирайте данные из блоков Gutenberg или из внешних сайтов через REST API.', 'fforms' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::FORM ) ); ?>" class="button button-primary"><?php esc_html_e( 'Добавить форму', 'fforms' ); ?></a>
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
				<?php if ( $can_view_entries ) : ?>
				<div class="fforms-card">
					<h2><?php esc_html_e( 'Заявки', 'fforms' ); ?></h2>
					<p class="fforms-card-status <?php echo esc_attr( $entries_count > 0 ? 'is-on' : 'is-off' ); ?>"><?php echo esc_html( sprintf( _n( '%d заявка', '%d заявок', $entries_count, 'fforms' ), $entries_count ) ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::ENTRY ) ); ?>"><?php esc_html_e( 'Все заявки →', 'fforms' ); ?></a></p>
				</div>
				<?php endif; ?>
			</div>

			<h2><?php esc_html_e( 'Частые вопросы', 'fforms' ); ?></h2>
			<div class="fforms-faq">
				<details id="fforms-faq-api" open>
					<summary><?php esc_html_e( 'Как быстро добавить приём сообщений через REST API?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'Главная форма работает сразу после активации плагина: создавать форму заранее не нужно.', 'fforms' ); ?></p>
					<dl>
						<dt><?php esc_html_e( 'Endpoint', 'fforms' ); ?></dt>
						<dd><code><?php echo esc_html( $main_url ); ?></code></dd>
						<dt><?php esc_html_e( 'Разрешённые origins', 'fforms' ); ?></dt>
						<dd><?php echo array() === $origins ? esc_html__( 'не заданы — кросс-доменные запросы заблокированы', 'fforms' ) : esc_html( implode( ', ', $origins ) ); ?> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=fforms-settings' ) ); ?>"><?php esc_html_e( 'изменить', 'fforms' ); ?></a></dd>
						<dt><?php esc_html_e( 'Строгий режим типов', 'fforms' ); ?></dt>
						<dd><?php echo $strict_types ? esc_html__( 'включён — принимаются только существующие типы', 'fforms' ) : esc_html__( 'выключен — новый тип создаётся автоматически', 'fforms' ); ?></dd>
						<dt><?php esc_html_e( 'Вложения', 'fforms' ); ?></dt>
						<dd><?php esc_html_e( 'не принимаются — файлы возвращают 400', 'fforms' ); ?></dd>
						<dt><?php esc_html_e( 'Типы форм', 'fforms' ); ?></dt>
						<dd>
							<?php if ( array() === $types ) : ?>
								<?php esc_html_e( 'пока нет — первый появится после первой заявки с formType', 'fforms' ); ?>
							<?php else : ?>
								<?php foreach ( $types as $term ) : ?>
									<code><?php echo esc_html( $term->slug ); ?></code> — <?php echo esc_html( $term->name ); ?><br>
								<?php endforeach; ?>
							<?php endif; ?>
						</dd>
					</dl>

					<?php foreach ( self::api_examples( $main_url ) as $index => $example ) : ?>
					<div class="fforms-example">
						<div class="fforms-example-head">
							<h3><?php echo esc_html( $example['title'] ); ?></h3>
							<button type="button" class="button button-small fforms-copy" data-target="fforms-example-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Скопировать', 'fforms' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html( $example['description'] ); ?></p>
						<pre id="fforms-example-<?php echo esc_attr( (string) $index ); ?>"><code><?php echo esc_html( $example['code'] ); ?></code></pre>
					</div>
					<?php endforeach; ?>
				</details>
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

			<script>
				document.querySelectorAll( '.fforms-copy' ).forEach( function ( button ) {
					button.addEventListener( 'click', function () {
						var source = document.getElementById( button.dataset.target );
						if ( ! source || ! navigator.clipboard ) {
							return;
						}
						navigator.clipboard.writeText( source.innerText ).then( function () {
							var label = button.textContent;
							button.textContent = <?php echo wp_json_encode( __( 'Скопировано', 'fforms' ) ); ?>;
							window.setTimeout( function () { button.textContent = label; }, 1500 );
						} );
					} );
				} );
			</script>
		</div>
		<?php
	}
}
