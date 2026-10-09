<?php
/**
 * Yandex SmartCaptcha (visible widget) adapter.
 *
 * @package FForms
 */

namespace FForms\Captcha;

final class Yandex_Smartcaptcha implements Provider {
	public function id(): string {
		return 'smartcaptcha';
	}

	public function label(): string {
		return 'Yandex SmartCaptcha';
	}

	public function script_url(): string {
		return 'https://smartcaptcha.yandexcloud.net/captcha.js?render=explicit';
	}

	public function verify_endpoint(): string {
		return 'https://smartcaptcha.yandexcloud.net/validate';
	}

	public function widget_container_attrs(): array {
		return array();
	}

	public function verify_params( string $token, string $ip ): array {
		return array(
			'secret' => Manager::server_key(),
			'token'  => $token,
			'ip'     => $ip,
		);
	}

	public function is_verified( array $response_body ): bool {
		return 'ok' === ( $response_body['status'] ?? '' );
	}
}
