<?php
/**
 * Value object addressing a form regardless of where it comes from.
 *
 * @package FForms
 */

namespace FForms;

final class Form_Ref {
	/**
	 * @param array{fields: array<int, array<string, mixed>>} $schema
	 * @param array<int, string>                               $origins
	 * @param array<string, mixed>                              $notifications
	 * @param 'post'|'code'|'builtin'                           $source
	 * @param ?string                                           $type Slug of the fform_type term entries inherit, if any.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly ?string $key,
		public readonly string $title,
		public readonly array $schema,
		public readonly string $success_message,
		public readonly array $origins,
		public readonly array $notifications,
		public readonly string $source,
		public readonly ?string $type = null
	) {}

	public function rate_key(): string {
		return 'post' === $this->source ? 'post:' . $this->post_id : 'code:' . $this->key;
	}
}
