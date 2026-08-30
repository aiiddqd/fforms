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
	 * @param 'post'|'code'                                     $source
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly ?string $key,
		public readonly string $title,
		public readonly array $schema,
		public readonly string $success_message,
		public readonly array $origins,
		public readonly array $notifications,
		public readonly string $source
	) {}

	public function rate_key(): string {
		return 'code' === $this->source ? 'code:' . $this->key : 'post:' . $this->post_id;
	}
}
