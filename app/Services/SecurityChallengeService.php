<?php

/**
 * @file SecurityChallengeService.php
 * @brief Short-lived, single-use browser authentication challenges.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\Session;
use RuntimeException;

/**
 * @brief Provides a reusable challenge mechanism for authentication methods and future second factors.
 */
final class SecurityChallengeService
{
	private const SESSION_KEY = 'pulse_security_challenges';
	private const MAX_AGE_SECONDS = 300;

	private Session $_session;

	/** @brief Constructs the challenge service. */
	public function __construct(Session $session)
	{
		$this->_session = $session;
	}

	/** @return array{challenge: string, context: array<string, mixed>} @brief Issues a new challenge for one purpose. */
	public function Issue(string $purpose, array $context = []): array
	{
		$challenge = $this->Base64UrlEncode(random_bytes(32));
		$challenges = $this->_session->Get(self::SESSION_KEY, []);
		$challenges = is_array($challenges) ? $challenges : [];
		$challenges[$purpose] = [
			'challenge' => $challenge,
			'created_at' => time(),
			'context' => $context,
		];
		$this->_session->Set(self::SESSION_KEY, $challenges);
		return ['challenge' => $challenge, 'context' => $context];
	}

	/** @return array{challenge: string, context: array<string, mixed>} @brief Consumes the current challenge for a purpose. */
	public function Consume(string $purpose): array
	{
		$challenges = $this->_session->Get(self::SESSION_KEY, []);
		$challenges = is_array($challenges) ? $challenges : [];
		$entry = $challenges[$purpose] ?? null;
		unset($challenges[$purpose]);
		$this->_session->Set(self::SESSION_KEY, $challenges);

		if (!is_array($entry) || !is_string($entry['challenge'] ?? null))
		{
			throw new RuntimeException('Authentication challenge is missing or has already been used.');
		}

		if ((time() - (int)($entry['created_at'] ?? 0)) > self::MAX_AGE_SECONDS)
		{
			throw new RuntimeException('Authentication challenge has expired.');
		}

		$context = $entry['context'] ?? [];
		return [
			'challenge' => (string)$entry['challenge'],
			'context' => is_array($context) ? $context : [],
		];
	}

	/** @brief Encodes challenge bytes for WebAuthn JSON. */
	private function Base64UrlEncode(string $value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
