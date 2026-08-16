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
		$location = is_array($config['location'] ?? null) ? $config['location'] : [];
		$geocodeOrigin = $this->HttpsOrigin((string)($location['reverse_geocode_url'] ?? ''));
		$tileOrigin = $this->HttpsOrigin((string)($location['map_tile_url'] ?? ''));
		$connectSources = trim("'self' " . $geocodeOrigin);
		$imageSources = trim("'self' data: " . $tileOrigin);

		header("Content-Security-Policy: default-src 'self'; base-uri 'self'; connect-src " . $connectSources . "; font-src 'self'; form-action 'self'; frame-ancestors 'none'; img-src " . $imageSources . "; object-src 'none'; script-src 'self'; style-src 'self'");
		header('Cross-Origin-Opener-Policy: same-origin');
		header('Cross-Origin-Resource-Policy: same-origin');
		header('Permissions-Policy: camera=(), geolocation=(self), microphone=(), payment=(), usb=()');
		header('Referrer-Policy: no-referrer');
		header('X-Content-Type-Options: nosniff');
		header('X-Frame-Options: DENY');

		if ($request->IsSecure() && (bool)($config['hsts_enabled'] ?? true))
		{
			header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
		}
	}

	/** @brief Returns one safely serialized HTTPS origin for CSP or an empty string. */
	private function HttpsOrigin(string $url): string
	{
		$parts = parse_url($url);

		if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https')
		{
			return '';
		}

		$host = strtolower((string)($parts['host'] ?? ''));

		if ($host === '' || preg_match('/^[a-z0-9.-]+$/', $host) !== 1)
		{
			return '';
		}

		$port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
		return 'https://' . $host . $port;
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
