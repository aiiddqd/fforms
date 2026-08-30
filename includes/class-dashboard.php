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

	public static function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$forms = get_posts(
			array(
				'post_type'      => Post_Types::FORM,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'numberposts'    => 5,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$total_forms = wp_count_posts( Post_Types::FORM );
		$total_forms = (int) ( $total_forms->publish ?? 0 ) + (int) ( $total_forms->draft ?? 0 ) + (int) ( $total_forms->private ?? 0 );
		$code_forms  = Registry\Code_Forms::all();

		$can_manage_entries = current_user_can( 'manage_options' );
		$entries            = $can_manage_entries ? get_posts(
			array(
				'post_type'   => Post_Types::ENTRY,
				'post_status' => 'private',
				'numberposts' => 5,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		) : array();
		?>
		<div class="wrap fforms-dashboard">
			<h1><?php esc_html_e( 'FForms', 'fforms' ); ?></h1>
			<p><?php esc_html_e( 'Лёгкий, headless-friendly приём форм: собирайте данные из блоков Gutenberg или из внешних сайтов через REST API.', 'fforms' ); ?></p>

			<p class="fforms-dashboard-actions">
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Types::FORM ) ); ?>" class="button button-primary"><?php esc_html_e( 'Добавить форму', 'fforms' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::FORM ) ); ?>" class="button"><?php esc_html_e( 'Все формы', 'fforms' ); ?></a>
			</p>

			<div id="fforms-dashboard-columns" style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;">
				<div style="flex:2; min-width:420px;">

					<h2><?php esc_html_e( 'Формы', 'fforms' ); ?> (<?php echo esc_html( (string) ( $total_forms + count( $code_forms ) ) ); ?>)</h2>
					<?php if ( array() === $forms && array() === $code_forms ) : ?>
						<p><?php esc_html_e( 'Пока нет ни одной формы.', 'fforms' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<tbody>
							<?php foreach ( $forms as $form_post ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link( $form_post ) ); ?>"><?php echo esc_html( get_the_title( $form_post ) ); ?></a></td>
									<td><?php echo esc_html( ucfirst( $form_post->post_status ) ); ?></td>
									<td><?php esc_html_e( 'Gutenberg', 'fforms' ); ?></td>
								</tr>
							<?php endforeach; ?>
							<?php foreach ( $code_forms as $key => $code_form ) : ?>
								<tr>
									<td><?php echo esc_html( $code_form->title ); ?></td>
									<td>—</td>
									<td><code><?php echo esc_html( (string) $key ); ?></code> (<?php esc_html_e( 'headless', 'fforms' ); ?>)</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::FORM ) ); ?>"><?php esc_html_e( 'Все формы →', 'fforms' ); ?></a></p>
					<?php endif; ?>

					<h2><?php esc_html_e( 'Headless-форма из кода', 'fforms' ); ?></h2>
					<p><?php esc_html_e( 'Регистрируйте форму на хуке fforms_register_forms — она станет доступна через REST API по своему ключу, без создания записи в Gutenberg.', 'fforms' ); ?></p>
					<pre style="background:#fff; border:1px solid #dcdcde; padding:12px 16px; overflow:auto;"><code>add_action( 'fforms_register_forms', function () {
	fforms_add_api_route( 'contact_astro', array(
		'title'   =&gt; 'Контакт (Astro)',
		'fields'  =&gt; array(
			array( 'name' =&gt; 'email', 'label' =&gt; 'Email', 'type' =&gt; 'email', 'required' =&gt; true ),
			array( 'name' =&gt; 'message', 'label' =&gt; 'Сообщение', 'type' =&gt; 'textarea', 'required' =&gt; true ),
		),
		'origins' =&gt; array( 'https://example.com' ),
	) );
} );</code></pre>

					<h2><?php esc_html_e( 'Пример запроса', 'fforms' ); ?></h2>
					<p><?php esc_html_e( 'Отправка данных формы с внешнего сайта:', 'fforms' ); ?></p>
					<pre style="background:#fff; border:1px solid #dcdcde; padding:12px 16px; overflow:auto;"><code>curl -X POST <?php echo esc_html( rest_url( 'fforms/v1/submit' ) ); ?> \
	-H "Content-Type: application/json" \
	-d '{
		"form_key": "contact_astro",
		"fields": { "email": "user@example.com", "message": "Hello!" }
	}'</code></pre>

				</div>

				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Последние заявки', 'fforms' ); ?></h2>
					<?php if ( ! $can_manage_entries ) : ?>
						<p><?php esc_html_e( 'Недостаточно прав для просмотра заявок.', 'fforms' ); ?></p>
					<?php elseif ( array() === $entries ) : ?>
						<p><?php esc_html_e( 'Заявок пока нет.', 'fforms' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $entries as $entry ) : ?>
								<?php
								$form_id  = (int) get_post_meta( $entry->ID, '_fforms_form_id', true );
								$form_key = (string) get_post_meta( $entry->ID, '_fforms_form_key', true );
								$title    = $form_id ? get_the_title( $form_id ) : $form_key;
								?>
								<li>
									<a href="<?php echo esc_url( get_edit_post_link( $entry ) ); ?>"><?php echo esc_html( $title ?: __( '(без формы)', 'fforms' ) ); ?></a>
									<br><small><?php echo esc_html( get_the_date( '', $entry ) . ' ' . get_the_time( '', $entry ) ); ?></small>
								</li>
							<?php endforeach; ?>
						</ul>
						<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::ENTRY ) ); ?>" class="button"><?php esc_html_e( 'Все заявки', 'fforms' ); ?></a></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
