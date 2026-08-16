<?php

/**
 * @file PasskeyService.php
 * @brief WebAuthn passkey registration and authentication.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use JsonException;
use Pulse\Core\CborDecoder;
use Pulse\Repositories\SecurityCredentialRepository;
use RuntimeException;

/**
 * @brief Implements the passkey authentication method on top of Pulse's generic security storage.
 */
final class PasskeyService
{
	private const FLAG_USER_PRESENT = 0x01;
	private const FLAG_USER_VERIFIED = 0x04;
	private const FLAG_BACKUP_ELIGIBLE = 0x08;
	private const FLAG_BACKUP_STATE = 0x10;
	private const FLAG_ATTESTED_CREDENTIAL_DATA = 0x40;
	private const ALG_ES256 = -7;
	private const ALG_RS256 = -257;

	private SecurityCredentialRepository $_credentials;
	private SecurityChallengeService $_challenges;
	private CborDecoder $_cbor;
	private string $_rpId;
	private string $_origin;
	private string $_rpName;

	/** @brief Constructs the passkey service from application configuration. */
	public function __construct(
		SecurityCredentialRepository $credentials,
		SecurityChallengeService $challenges,
		array $config
	)
	{
		$this->_credentials = $credentials;
		$this->_challenges = $challenges;
		$this->_cbor = new CborDecoder();
		$this->_rpName = (string)($config['name'] ?? 'Pulse');
		$this->_origin = $this->NormalizeOrigin((string)($config['base_url'] ?? ''));
		$host = parse_url($this->_origin, PHP_URL_HOST);

		if (!is_string($host) || $host === '')
		{
			throw new RuntimeException('Passkeys require a valid Pulse base URL host.');
		}

		$this->_rpId = strtolower($host);
	}

	/** @return array<string, mixed> @brief Starts passkey registration for an authenticated user. */
	public function BeginRegistration(array $user, string $label): array
	{
		$userId = (int)($user['id'] ?? 0);

		if ($userId < 1)
		{
			throw new RuntimeException('Cannot register a passkey without a valid account.');
		}

		$label = trim($label);

		if ($label === '')
		{
			throw new RuntimeException('A passkey name is required.');
		}

		$handle = $this->_credentials->GetOrCreateWebAuthnUserHandle($userId);
		$challenge = $this->_challenges->Issue('passkey-register', [
			'user_id' => $userId,
			'label' => substr($label, 0, 255),
		]);
		$exclude = [];

		foreach ($this->_credentials->FindPasskeysForUser($userId) as $passkey)
		{
			$entry = [
				'type' => 'public-key',
				'id' => (string)$passkey['credential_id'],
			];
			$transports = $this->TransportList((string)($passkey['transports'] ?? ''));

			if ($transports !== [])
			{
				$entry['transports'] = $transports;
			}

			$exclude[] = $entry;
		}

		return [
			'challenge' => $challenge['challenge'],
			'rp' => [
				'name' => $this->_rpName,
				'id' => $this->_rpId,
			],
			'user' => [
				'id' => $handle,
				'name' => (string)($user['email'] ?? ''),
				'displayName' => (string)($user['display_name'] ?? $user['email'] ?? 'Pulse user'),
			],
			'pubKeyCredParams' => [
				['type' => 'public-key', 'alg' => self::ALG_ES256],
				['type' => 'public-key', 'alg' => self::ALG_RS256],
			],
			'timeout' => 60000,
			'attestation' => 'none',
			'authenticatorSelection' => [
				'residentKey' => 'required',
				'requireResidentKey' => true,
				'userVerification' => 'required',
			],
			'excludeCredentials' => $exclude,
		];
	}

	/** @return array{id: int, label: string} @brief Completes and persists passkey registration. */
	public function CompleteRegistration(int $currentUserId, array $response): array
	{
		$challenge = $this->_challenges->Consume('passkey-register');
		$context = $challenge['context'];

		if ((int)($context['user_id'] ?? 0) !== $currentUserId)
		{
			throw new RuntimeException('Passkey registration account changed during the ceremony.');
		}

		$clientDataJson = $this->DecodeField($response, 'client_data_json');
		$this->VerifyClientData($clientDataJson, 'webauthn.create', $challenge['challenge']);
		$attestationBytes = $this->DecodeField($response, 'attestation_object');
		$credentialId = $this->DecodeField($response, 'credential_id');
		$attestation = $this->_cbor->Decode($attestationBytes);

		if (!is_array($attestation)
			|| !isset($attestation['authData'])
			|| !is_string($attestation['authData'])
			|| !array_key_exists('attStmt', $attestation)
			|| !is_array($attestation['attStmt']))
		{
			throw new RuntimeException('Invalid passkey attestation object.');
		}

		if ((string)($attestation['fmt'] ?? '') !== 'none' || $attestation['attStmt'] !== [])
		{
			throw new RuntimeException('Unexpected passkey attestation format.');
		}

		$auth = $this->ParseAuthenticatorData((string)$attestation['authData'], true);

		if (!hash_equals($credentialId, (string)$auth['credential_id']))
		{
			throw new RuntimeException('Passkey credential ID does not match the attested credential.');
		}

		$coseKey = $auth['cose_key'];

		if (!is_array($coseKey))
		{
			throw new RuntimeException('Passkey attestation is missing a credential public key.');
		}

		$algorithm = (int)($coseKey[3] ?? 0);
		$publicKeyPem = $this->CosePublicKeyToPem($coseKey, $algorithm);
		$transports = $this->NormalizeTransports((string)($response['transports'] ?? ''));
		$label = substr(trim((string)($context['label'] ?? 'Passkey')), 0, 255);
		$id = $this->_credentials->AddPasskey(
			$currentUserId,
			$label !== '' ? $label : 'Passkey',
			$credentialId,
			$publicKeyPem,
			$algorithm,
			(int)$auth['sign_count'],
			$transports
		);

		return ['id' => $id, 'label' => $label];
	}

	/** @return array<string, mixed> @brief Starts passkey authentication for login or another security purpose. */
	public function BeginAuthentication(string $purpose, ?int $userId = null): array
	{
		$context = [];
		$allow = [];

		if ($userId !== null)
		{
			$context['user_id'] = $userId;

			foreach ($this->_credentials->FindPasskeysForUser($userId) as $passkey)
			{
				$entry = [
					'type' => 'public-key',
					'id' => (string)$passkey['credential_id'],
				];
				$transports = $this->TransportList((string)($passkey['transports'] ?? ''));

				if ($transports !== [])
				{
					$entry['transports'] = $transports;
				}

				$allow[] = $entry;
			}
		}

		$challenge = $this->_challenges->Issue('passkey-auth-' . $purpose, $context);
		$result = [
			'challenge' => $challenge['challenge'],
			'rpId' => $this->_rpId,
			'timeout' => 60000,
			'userVerification' => 'required',
		];

		if ($allow !== [])
		{
			$result['allowCredentials'] = $allow;
		}

		return $result;
	}

	/** @brief Verifies a passkey assertion and returns the authenticated user ID. */
	public function CompleteAuthentication(string $purpose, array $response): int
	{
		$challenge = $this->_challenges->Consume('passkey-auth-' . $purpose);
		$clientDataJson = $this->DecodeField($response, 'client_data_json');
		$this->VerifyClientData($clientDataJson, 'webauthn.get', $challenge['challenge']);
		$credentialId = $this->DecodeField($response, 'credential_id');
		$credential = $this->_credentials->FindPasskeyByCredentialId($credentialId);

		if (!is_array($credential) || empty($credential['is_active']))
		{
			throw new RuntimeException('Passkey is not registered to an active Pulse account.');
		}

		$userId = (int)$credential['user_id'];
		$expectedUserId = (int)($challenge['context']['user_id'] ?? 0);

		if ($expectedUserId > 0 && $userId !== $expectedUserId)
		{
			throw new RuntimeException('Passkey belongs to a different Pulse account.');
		}

		$userHandleEncoded = trim((string)($response['user_handle'] ?? ''));

		if ($expectedUserId === 0 && $userHandleEncoded === '')
		{
			throw new RuntimeException('Discoverable passkey authentication did not identify a user handle.');
		}

		if ($userHandleEncoded !== '')
		{
			$userHandle = $this->Base64UrlDecode($userHandleEncoded);
			$expectedHandle = $this->Base64UrlDecode((string)$credential['webauthn_user_handle']);

			if (!hash_equals($expectedHandle, $userHandle))
			{
				throw new RuntimeException('Passkey user handle does not match the account.');
			}
		}

		$authenticatorData = $this->DecodeField($response, 'authenticator_data');
		$auth = $this->ParseAuthenticatorData($authenticatorData, false);
		$signature = $this->DecodeField($response, 'signature');
		$signedData = $authenticatorData . hash('sha256', $clientDataJson, true);
		$algorithm = (int)$credential['algorithm'];
		$opensslAlgorithm = match ($algorithm)
		{
			self::ALG_ES256, self::ALG_RS256 => OPENSSL_ALGO_SHA256,
			default => throw new RuntimeException('Unsupported stored passkey algorithm.'),
		};
		$verified = openssl_verify($signedData, $signature, (string)$credential['public_key_pem'], $opensslAlgorithm);

		if ($verified !== 1)
		{
			throw new RuntimeException('Passkey signature verification failed.');
		}

		$storedCount = (int)$credential['sign_count'];
		$newCount = (int)$auth['sign_count'];

		if ($storedCount > 0 && $newCount > 0 && $newCount <= $storedCount)
		{
			throw new RuntimeException('Passkey signature counter did not advance.');
		}

		$this->_credentials->MarkPasskeyUsed((int)$credential['id'], max($storedCount, $newCount));
		return $userId;
	}

	/** @brief Returns whether an account has a registered passkey. */
	public function HasPasskeys(int $userId): bool
	{
		return $this->_credentials->HasPasskeys($userId);
	}

	/** @brief Verifies WebAuthn client data against the expected ceremony. */
	private function VerifyClientData(string $json, string $expectedType, string $expectedChallenge): void
	{
		try
		{
			$data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception)
		{
			throw new RuntimeException('Invalid WebAuthn client data.', 0, $exception);
		}

		if (!is_array($data)
			|| !hash_equals($expectedType, (string)($data['type'] ?? ''))
			|| !hash_equals($expectedChallenge, (string)($data['challenge'] ?? ''))
			|| !hash_equals($this->_origin, $this->NormalizeOrigin((string)($data['origin'] ?? '')))
			|| !empty($data['crossOrigin']))
		{
			throw new RuntimeException('WebAuthn client data did not match this Pulse request.');
		}
	}

	/** @return array<string, mixed> @brief Parses and validates WebAuthn authenticator data. */
	private function ParseAuthenticatorData(string $data, bool $registration): array
	{
		if (strlen($data) < 37)
		{
			throw new RuntimeException('Authenticator data is truncated.');
		}

		$rpIdHash = substr($data, 0, 32);

		if (!hash_equals(hash('sha256', $this->_rpId, true), $rpIdHash))
		{
			throw new RuntimeException('Passkey was issued for a different relying party.');
		}

		$flags = ord($data[32]);

		if (($flags & self::FLAG_USER_PRESENT) === 0 || ($flags & self::FLAG_USER_VERIFIED) === 0)
		{
			throw new RuntimeException('Passkey authentication did not verify the user.');
		}

		if (($flags & self::FLAG_BACKUP_STATE) !== 0 && ($flags & self::FLAG_BACKUP_ELIGIBLE) === 0)
		{
			throw new RuntimeException('Passkey authenticator data contains an invalid backup state.');
		}

		$counter = unpack('Nvalue', substr($data, 33, 4));
		$signCount = is_array($counter) ? (int)($counter['value'] ?? 0) : 0;
		$result = [
			'flags' => $flags,
			'sign_count' => $signCount,
			'credential_id' => null,
			'cose_key' => null,
		];

		if (!$registration)
		{
			return $result;
		}

		if (($flags & self::FLAG_ATTESTED_CREDENTIAL_DATA) === 0 || strlen($data) < 55)
		{
			throw new RuntimeException('Passkey registration is missing attested credential data.');
		}

		$lengthData = unpack('nvalue', substr($data, 53, 2));
		$credentialLength = is_array($lengthData) ? (int)($lengthData['value'] ?? 0) : 0;
		$offset = 55;

		if ($credentialLength < 1 || $offset + $credentialLength > strlen($data))
		{
			throw new RuntimeException('Passkey credential ID is truncated.');
		}

		$result['credential_id'] = substr($data, $offset, $credentialLength);
		$offset += $credentialLength;
		$result['cose_key'] = $this->_cbor->DecodeAt($data, $offset);
		return $result;
	}

	/** @brief Converts a supported COSE public key into PEM SubjectPublicKeyInfo. */
	private function CosePublicKeyToPem(array $cose, int $algorithm): string
	{
		if ($algorithm === self::ALG_ES256)
		{
			if ((int)($cose[1] ?? 0) !== 2 || (int)($cose[-1] ?? 0) !== 1 || !is_string($cose[-2] ?? null) || !is_string($cose[-3] ?? null))
			{
				throw new RuntimeException('Invalid ES256 passkey public key.');
			}

			$x = (string)$cose[-2];
			$y = (string)$cose[-3];

			if (strlen($x) !== 32 || strlen($y) !== 32)
			{
				throw new RuntimeException('Invalid ES256 passkey coordinate length.');
			}

			$algorithmIdentifier = hex2bin('301306072a8648ce3d020106082a8648ce3d030107');
			$subjectPublicKey = "\x04" . $x . $y;
			$der = $this->DerSequence((string)$algorithmIdentifier . $this->DerBitString($subjectPublicKey));
			return $this->Pem('PUBLIC KEY', $der);
		}

		if ($algorithm === self::ALG_RS256)
		{
			if ((int)($cose[1] ?? 0) !== 3 || !is_string($cose[-1] ?? null) || !is_string($cose[-2] ?? null))
			{
				throw new RuntimeException('Invalid RS256 passkey public key.');
			}

			$rsa = $this->DerSequence($this->DerInteger((string)$cose[-1]) . $this->DerInteger((string)$cose[-2]));
			$algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
			$der = $this->DerSequence((string)$algorithmIdentifier . $this->DerBitString($rsa));
			return $this->Pem('PUBLIC KEY', $der);
		}

		throw new RuntimeException('Authenticator selected an unsupported passkey algorithm.');
	}

	/** @brief Wraps binary content in an ASN.1 DER sequence. */
	private function DerSequence(string $content): string
	{
		return "\x30" . $this->DerLength(strlen($content)) . $content;
	}

	/** @brief Encodes a positive ASN.1 DER integer. */
	private function DerInteger(string $bytes): string
	{
		$bytes = ltrim($bytes, "\x00");
		$bytes = $bytes === '' ? "\x00" : $bytes;

		if ((ord($bytes[0]) & 0x80) !== 0)
		{
			$bytes = "\x00" . $bytes;
		}

		return "\x02" . $this->DerLength(strlen($bytes)) . $bytes;
	}

	/** @brief Encodes an ASN.1 DER bit string with zero unused bits. */
	private function DerBitString(string $bytes): string
	{
		$content = "\x00" . $bytes;
		return "\x03" . $this->DerLength(strlen($content)) . $content;
	}

	/** @brief Encodes an ASN.1 DER length. */
	private function DerLength(int $length): string
	{
		if ($length < 128)
		{
			return chr($length);
		}

		$bytes = '';
		$value = $length;

		while ($value > 0)
		{
			$bytes = chr($value & 0xff) . $bytes;
			$value >>= 8;
		}

		return chr(0x80 | strlen($bytes)) . $bytes;
	}

	/** @brief Builds a PEM block from DER bytes. */
	private function Pem(string $type, string $der): string
	{
		return '-----BEGIN ' . $type . "-----\n"
			. chunk_split(base64_encode($der), 64, "\n")
			. '-----END ' . $type . "-----\n";
	}

	/** @brief Decodes one required base64url form field. */
	private function DecodeField(array $response, string $key): string
	{
		$value = (string)($response[$key] ?? '');

		if ($value === '')
		{
			throw new RuntimeException('Passkey response is incomplete.');
		}

		return $this->Base64UrlDecode($value);
	}

	/** @brief Decodes base64url without accepting malformed input. */
	private function Base64UrlDecode(string $value): string
	{
		if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1)
		{
			throw new RuntimeException('Invalid base64url value.');
		}

		$padding = (4 - (strlen($value) % 4)) % 4;
		$decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);

		if (!is_string($decoded))
		{
			throw new RuntimeException('Invalid base64url encoding.');
		}

		return $decoded;
	}

	/** @brief Normalizes a Pulse base URL or WebAuthn origin. */
	private function NormalizeOrigin(string $value): string
	{
		$parts = parse_url(trim($value));

		if (!is_array($parts) || !isset($parts['scheme'], $parts['host']))
		{
			return '';
		}

		$scheme = strtolower((string)$parts['scheme']);
		$host = strtolower((string)$parts['host']);
		$port = isset($parts['port']) ? (int)$parts['port'] : null;
		$defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
		return $scheme . '://' . $host . ($port !== null && !$defaultPort ? ':' . $port : '');
	}

	/** @return array<int, string> @brief Converts a stored transport CSV to browser values. */
	private function TransportList(string $value): array
	{
		return $this->NormalizeTransports($value);
	}

	/** @return array<int, string> @brief Keeps only known WebAuthn authenticator transports. */
	private function NormalizeTransports(string $value): array
	{
		$allowed = ['usb', 'nfc', 'ble', 'internal', 'hybrid', 'smart-card'];
		$items = array_filter(array_map('trim', explode(',', $value)));
		$items = array_values(array_unique(array_filter($items, static fn (string $item): bool => in_array($item, $allowed, true))));
		return $items;
	}
}
