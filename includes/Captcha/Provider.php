<?php
/**
 * Captcha provider contract: one adapter per third-party service.
 *
 * The provider hides the protocol differences (script URL, verification
 * endpoint, request and response field names) behind a single interface.
 * The manager owns keys, routing, and error policy.
 *
 * @package FForms
 */

namespace FForms\Captcha;

interface Provider {
	/** Short stable id used in settings, error codes, and data attributes. */
	public function id(): string;

	/** Human-readable brand name for the admin provider select. */
	public function label(): string;

	/** URL of the provider widget API loaded with render=explicit. */
	public function script_url(): string;

	/** Endpoint that verifies a token server-side. */
	public function verify_endpoint(): string;

	/**
	 * Extra data attributes for the widget container. The manager merges
	 * data-provider, data-sitekey, a unique id, and the base class into them.
	 *
	 * @return array<string, string>
	 */
	public function widget_container_attrs(): array;

	/**
	 * POST body for the verification request. The server key comes from
	 * Manager::server_key() so the constant override applies everywhere.
	 *
	 * @return array<string, string>
	 */
	public function verify_params( string $token, string $ip ): array;

	/** Whether the decoded verification response confirms the token. */
	public function is_verified( array $response_body ): bool;
}
