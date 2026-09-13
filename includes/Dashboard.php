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
		add_action( 'admin_menu', array( self::class, 'order_submenu' ), 100 );
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

		// Final placement is done by self::order_submenu(); the CPT-generated
		// "Forms"/"Submissions" items land in $submenu['fforms'] before this hook runs.
		add_submenu_page(
			'fforms',
			__( 'Overview', 'fforms' ),
			__( 'Overview', 'fforms' ),
			'edit_posts',
			self::PAGE,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Submissions are the daily job, forms are set up once — so the menu reads
	 * overview, submissions, then the form setup screens and the settings.
	 * Slugs missing from the list keep their relative order at the bottom.
	 */
	private const SUBMENU_ORDER = array(
		self::PAGE,
		'edit.php?post_type=' . Post_Types::ENTRY,
		'edit.php?post_type=' . Post_Types::FORM,
		'post-new.php?post_type=' . Post_Types::FORM,
		'edit-tags.php?taxonomy=' . Form_Types::TAXONOMY . '&post_type=' . Post_Types::ENTRY,
		'fforms-settings',
		'fforms-export',
	);

	/**
	 * The top-level slug stays `fforms` — both CPTs use it as show_in_menu and
	 * the settings/export pages as their parent. Only the overview page moved,
	 * so the auto-generated duplicate entry goes away before we sort.
	 */
	public static function order_submenu(): void {
		remove_submenu_page( 'fforms', 'fforms' );

		global $submenu;
		if ( empty( $submenu['fforms'] ) ) {
			return;
		}

		$rank = array_flip( self::SUBMENU_ORDER );
		$last = count( self::SUBMENU_ORDER );
		$keyed = array();
		foreach ( array_values( $submenu['fforms'] ) as $i => $item ) {
			$keyed[] = array( $rank[ $item[2] ] ?? $last, $i, $item );
		}
		usort( $keyed, static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ) );

		$submenu['fforms'] = array_column( $keyed, 2 );
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
				'title'       => __( 'Contact form', 'fforms' ),
				'description' => __( 'Without formType the submission gets the “main” type.', 'fforms' ),
				'code'        => $curl( "{\n    \"name\": \"John\",\n    \"email\": \"john@example.com\",\n    \"message\": \"Hello!\"\n  }" ),
			),
			array(
				'title'       => __( 'Consultation request', 'fforms' ),
				'description' => __( 'formType creates a form type: rename the term in the admin, the slug stays the same.', 'fforms' ),
				'code'        => $curl( "{\n    \"formType\": \"consultation_request\",\n    \"name\": \"John\",\n    \"phone\": \"+1 555 010 0000\",\n    \"email\": \"john@example.com\"\n  }" ),
			),
			array(
				'title'       => __( 'Arbitrary fields', 'fforms' ),
				'description' => __( 'There is no schema: every key you send is stored as a field of the submission.', 'fforms' ),
				'code'        => $curl( "{\n    \"formType\": \"partner\",\n    \"email\": \"sales@acme.dev\",\n    \"company\": \"Acme\",\n    \"budget\": \"5000\"\n  }" ),
			),
			array(
				'title'       => __( 'Submission with a UTM tag', 'fforms' ),
				'description' => __( 'ref and meta are reserved keys: they keep the request context separate from the submitted fields.', 'fforms' ),
				'code'        => $curl( "{\n    \"message\": \"Please call me back\",\n    \"phone\": \"+1 555 010 0000\",\n    \"ref\": \"yandex-direct\",\n    \"meta\": { \"page\": \"/pricing\", \"locale\": \"en\" }\n  }" ),
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
				<p><?php esc_html_e( 'Lightweight, headless-friendly form handling: collect data from Gutenberg blocks or from external sites through the REST API.', 'fforms' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::FORM ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add form', 'fforms' ); ?></a>
					<a href="<?php echo esc_url( self::REPO_URL ); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read the documentation', 'fforms' ); ?></a>
				</p>
			</div>

			<div class="fforms-cards">
				<?php if ( $can_view_entries ) : ?>
				<div class="fforms-card">
					<h2><?php esc_html_e( 'Submissions', 'fforms' ); ?></h2>
					<p class="fforms-card-status <?php echo esc_attr( $entries_count > 0 ? 'is-on' : 'is-off' ); ?>"><?php echo esc_html( sprintf( _n( '%d submission', '%d submissions', $entries_count, 'fforms' ), $entries_count ) ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::ENTRY ) ); ?>"><?php esc_html_e( 'All submissions →', 'fforms' ); ?></a></p>
				</div>
				<?php endif; ?>
				<div class="fforms-card">
					<h2><?php esc_html_e( 'Forms', 'fforms' ); ?></h2>
					<p class="fforms-card-status"><?php echo esc_html( sprintf( _n( '%d form', '%d forms', $forms_count, 'fforms' ), $forms_count ) ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::FORM ) ); ?>"><?php esc_html_e( 'All forms →', 'fforms' ); ?></a></p>
				</div>
				<div class="fforms-card">
					<h2><?php esc_html_e( 'SMTP', 'fforms' ); ?></h2>
					<?php if ( $smtp['enabled'] ) : ?>
						<p class="fforms-card-status is-on"><?php esc_html_e( 'Enabled', 'fforms' ); ?></p>
						<p><?php echo esc_html( $smtp['host'] ?: __( 'Host is not set', 'fforms' ) ); ?></p>
					<?php else : ?>
						<p class="fforms-card-status is-off"><?php esc_html_e( 'Disabled', 'fforms' ); ?></p>
						<p><?php esc_html_e( 'Email is sent through the default wp_mail()', 'fforms' ); ?></p>
					<?php endif; ?>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fforms-settings' ) ); ?>"><?php esc_html_e( 'Settings →', 'fforms' ); ?></a></p>
				</div>
				<div class="fforms-card">
					<h2><?php esc_html_e( 'Forms in code', 'fforms' ); ?></h2>
					<?php if ( array() === $code_forms ) : ?>
						<p class="fforms-card-status is-off"><?php esc_html_e( 'Not in use', 'fforms' ); ?></p>
					<?php else : ?>
						<p class="fforms-card-status is-on"><?php echo esc_html( sprintf( _n( '%d form in code', '%d forms in code', count( $code_forms ), 'fforms' ), count( $code_forms ) ) ); ?></p>
					<?php endif; ?>
					<p><a href="#fforms-faq-headless"><?php esc_html_e( 'How to connect →', 'fforms' ); ?></a></p>
				</div>
			</div>

			<h2><?php esc_html_e( 'Questions and answers', 'fforms' ); ?></h2>
			<div class="fforms-faq">
				<details id="fforms-faq-api" open>
					<summary><?php esc_html_e( 'How do I start receiving messages through the REST API?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'The route works right after the plugin is activated: there is no form to create and no schema to declare. Whatever top-level keys you send are stored as the submission; formType classifies it so submissions can be filtered.', 'fforms' ); ?></p>
					<dl>
						<dt><?php esc_html_e( 'Endpoint', 'fforms' ); ?></dt>
						<dd><code><?php echo esc_html( $main_url ); ?></code></dd>
						<dt><?php esc_html_e( 'Allowed origins', 'fforms' ); ?></dt>
						<dd><?php echo array() === $origins ? esc_html__( 'not set — cross-origin requests are blocked', 'fforms' ) : esc_html( implode( ', ', $origins ) ); ?> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=fforms-settings' ) ); ?>"><?php esc_html_e( 'change', 'fforms' ); ?></a></dd>
						<dt><?php esc_html_e( 'Strict form types', 'fforms' ); ?></dt>
						<dd><?php echo $strict_types ? esc_html__( 'on — only existing types are accepted', 'fforms' ) : esc_html__( 'off — a new type is created automatically', 'fforms' ); ?></dd>
						<dt><?php esc_html_e( 'Attachments', 'fforms' ); ?></dt>
						<dd><?php esc_html_e( 'not accepted — files return 400', 'fforms' ); ?></dd>
						<dt><?php esc_html_e( 'Form types', 'fforms' ); ?></dt>
						<dd>
							<?php if ( array() === $types ) : ?>
								<?php esc_html_e( 'none yet — the first one appears after the first submission with formType', 'fforms' ); ?>
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
							<button type="button" class="button button-small fforms-copy" data-target="fforms-example-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Copy', 'fforms' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html( $example['description'] ); ?></p>
						<pre id="fforms-example-<?php echo esc_attr( (string) $index ); ?>"><code><?php echo esc_html( $example['code'] ); ?></code></pre>
					</div>
					<?php endforeach; ?>
				</details>
				<details>
					<summary><?php esc_html_e( 'How do I create a form?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'Click “Add form”, build the fields with FForms blocks right in the Gutenberg editor and publish the post. A published form can be inserted with the “FForms Form” block or the [fform id=…] shortcode; turning on “Share via link” in the Publication panel also gives it a secret URL plus iframe and js-script snippets for external sites.', 'fforms' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'How do I set up email sending?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'In “Settings”, enable the built-in SMTP and fill in the host, port and username — or leave it off when another SMTP plugin already handles email. Notifications and auto-replies for a particular form are configured in the form itself.', 'fforms' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'How do I export submissions?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'In “CSV export”, pick a form (or all of them) and click “Download CSV”.', 'fforms' ); ?></p>
				</details>
				<details id="fforms-faq-headless">
					<summary><?php esc_html_e( 'How do I add a headless form (a form defined in code)?', 'fforms' ); ?></summary>
					<p><?php esc_html_e( 'Register the form on the fforms_register_forms hook — it becomes available through the REST API under its key, with no post created in Gutenberg.', 'fforms' ); ?></p>
					<pre><code>add_action( 'fforms_register_forms', function () {
	fforms_add_api_route( 'contact_astro', array(
		'title'   =&gt; 'Contact (Astro)',
		'fields'  =&gt; array(
			array( 'name' =&gt; 'email', 'label' =&gt; 'Email', 'type' =&gt; 'email', 'required' =&gt; true ),
			array( 'name' =&gt; 'message', 'label' =&gt; 'Message', 'type' =&gt; 'textarea', 'required' =&gt; true ),
		),
		'origins' =&gt; array( 'https://example.com' ),
	) );
} );</code></pre>
					<p><?php esc_html_e( 'Submitting form data from an external site:', 'fforms' ); ?></p>
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
							button.textContent = <?php echo wp_json_encode( __( 'Copied', 'fforms' ) ); ?>;
							window.setTimeout( function () { button.textContent = label; }, 1500 );
						} );
					} );
				} );
			</script>
		</div>
		<?php
	}
}
