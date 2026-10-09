<?php
/**
 * Cloudflare Turnstile (managed) adapter.
 *
 * @package FForms
 */

namespace FForms\Captcha;

final class Cloudflare_Turnstile implements Provider {
	public function id(): string {
		return 'turnstile';
	}

	public function label(): string {
		return 'Cloudflare Turnstile';
	}

	public function script_url(): string {
		return 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
	}

	public function verify_endpoint(): string {
		return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	}

	public function widget_container_attrs(): array {
		return array();
	}

	public function verify_params( string $token, string $ip ): array {
		return array(
			'secret'   => Manager::server_key(),
			'response' => $token,
			'remoteip' => $ip,
		);
	}

	public function is_verified( array $response_body ): bool {
		return true === ( $response_body['success'] ?? false );
	}
}
