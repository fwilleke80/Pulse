<?php

/**
 * @file SecurityHeaders.php
 * @brief Applies HTTP response security headers and validates trusted hosts.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Central response-hardening policy.
 */
final class SecurityHeaders
{
	/**
	 * @brief Applies the configured response security policy.
	 * @param Request $request Current request.
	 * @param array<string, mixed> $config Security configuration.
	 */
	public function Apply(Request $request, array $config): void
	{
		$this->ValidateHost($request, $config);

		header("Content-Security-Policy: default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self'");
		header('Cross-Origin-Opener-Policy: same-origin');
		header('Cross-Origin-Resource-Policy: same-origin');
		header('Permissions-Policy: camera=(), geolocation=(), microphone=(), payment=(), usb=()');
		header('Referrer-Policy: no-referrer');
		header('X-Content-Type-Options: nosniff');
		header('X-Frame-Options: DENY');

		if ($request->IsSecure() && (bool)($config['hsts_enabled'] ?? true))
		{
			header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
		}
	}

	/**
	 * @brief Rejects a request whose host is not explicitly trusted.
	 * @param Request $request Current request.
	 * @param array<string, mixed> $config Security configuration.
	 */
	private function ValidateHost(Request $request, array $config): void
	{
		$trustedHosts = $config['trusted_hosts'] ?? [];

		if (!is_array($trustedHosts) || $trustedHosts === [])
		{
			return;
		}

		$host = $request->Host();

		if ($host === '' || !in_array($host, $trustedHosts, true))
		{
			http_response_code(400);
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Invalid request host.';
			exit;
		}
	}
}
