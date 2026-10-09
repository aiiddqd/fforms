<?php
/**
 * Google reCAPTCHA v2 (checkbox) adapter.
 *
 * @package FForms
 */

namespace FForms\Captcha;

final class Google_Recaptcha implements Provider {
	public function id(): string {
		return 'recaptcha';
	}

	public function label(): string {
		return 'Google reCAPTCHA';
	}

	public function script_url(): string {
		return 'https://www.google.com/recaptcha/api.js?render=explicit';
	}

	public function verify_endpoint(): string {
		return 'https://www.google.com/recaptcha/api/siteverify';
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
