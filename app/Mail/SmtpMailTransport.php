<?php

/**
 * @file SmtpMailTransport.php
 * @brief Authenticated SMTP client with implicit TLS and STARTTLS support.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Mail;

use Pulse\Core\MarkdownRenderer;

/**
 * @brief Sends UTF-8 multipart messages with plain-text and Markdown-rendered HTML alternatives.
 */
final class SmtpMailTransport implements MailTransportInterface
{
	/** @var array<string, mixed> */
	private array $_config;
	private MarkdownRenderer $_markdownRenderer;

	/**
	 * @brief Constructs the SMTP transport.
	 * @param array<string, mixed> $config Validated mail configuration.
	 * @param MarkdownRenderer|null $markdownRenderer Shared Markdown renderer.
	 */
	public function __construct(array $config, ?MarkdownRenderer $markdownRenderer = null)
	{
		$this->_config = $config;
		$this->_markdownRenderer = $markdownRenderer ?? new MarkdownRenderer();
	}

	/** @inheritDoc */
	public function Send(string $recipientEmail, string $subject, string $bodyText): void
	{
		$this->AssertHeaderValue($recipientEmail, 'recipient address');
		$this->AssertHeaderValue($subject, 'subject');

		if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false)
		{
			throw new MailTransportException('The queued recipient address is invalid.');
		}

		$host = (string)$this->_config['host'];
		$port = (int)$this->_config['port'];
		$encryption = (string)$this->_config['encryption'];
		$timeout = (int)$this->_config['timeout_seconds'];
		$scheme = $encryption === 'tls' ? 'tls' : 'tcp';
		$connectionHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $host . ']' : $host;
		$context = stream_context_create([
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
				'peer_name' => $host,
				'SNI_enabled' => true,
			],
		]);
		$errorNumber = 0;
		$errorMessage = '';
		$socket = @stream_socket_client(
			$scheme . '://' . $connectionHost . ':' . $port,
			$errorNumber,
			$errorMessage,
			$timeout,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if (!is_resource($socket))
		{
			throw new MailTransportException('Unable to connect to the SMTP server.');
		}

		stream_set_timeout($socket, $timeout);

		try
		{
			$this->Expect($socket, [220]);
			$capabilities = $this->Ehlo($socket);

			if ($encryption === 'starttls')
			{
				if (!isset($capabilities['STARTTLS']))
				{
					throw new MailTransportException('The SMTP server does not advertise STARTTLS.');
				}

				$this->Command($socket, 'STARTTLS', [220]);
				$cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

				if ($cryptoEnabled !== true)
				{
					throw new MailTransportException('Unable to establish SMTP TLS encryption.');
				}

				$capabilities = $this->Ehlo($socket);
			}

			$this->Authenticate($socket, $capabilities);
			$fromAddress = (string)$this->_config['from_address'];
			$this->Command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
			$this->Command($socket, 'RCPT TO:<' . $recipientEmail . '>', [250, 251]);
			$this->Command($socket, 'DATA', [354]);
			$this->Write($socket, $this->BuildMessage($recipientEmail, $subject, $bodyText) . "\r\n.\r\n");
			$this->Expect($socket, [250]);
			$this->Command($socket, 'QUIT', [221]);
		}
		finally
		{
			fclose($socket);
		}
	}

	/**
	 * @brief Sends EHLO and returns advertised capabilities.
	 * @param resource $socket SMTP socket.
	 * @return array<string, string>
	 */
	private function Ehlo($socket): array
	{
		$hostName = gethostname();
		$hostName = is_string($hostName) && $hostName !== '' ? $hostName : 'localhost';
		$hostName = preg_replace('/[^a-zA-Z0-9.-]/', '-', $hostName) ?? 'localhost';
		$response = $this->Command($socket, 'EHLO ' . $hostName, [250]);
		$capabilities = [];

		foreach (preg_split('/\r\n|\n|\r/', $response) ?: [] as $line)
		{
			$capability = trim(substr($line, 4));

			if ($capability === '')
			{
				continue;
			}

			[$name, $value] = array_pad(preg_split('/\s+/', $capability, 2) ?: [], 2, '');

			if (str_starts_with(strtoupper($name), 'AUTH='))
			{
				$value = substr($name, 5) . ($value !== '' ? ' ' . $value : '');
				$name = 'AUTH';
			}

			$capabilities[strtoupper($name)] = strtoupper($value);
		}

		return $capabilities;
	}

	/**
	 * @brief Authenticates when credentials are configured.
	 * @param resource $socket SMTP socket.
	 * @param array<string, string> $capabilities Server capabilities.
	 */
	private function Authenticate($socket, array $capabilities): void
	{
		$username = (string)$this->_config['username'];
		$password = (string)$this->_config['password'];

		if ($username === '')
		{
			return;
		}

		$methods = ' ' . ($capabilities['AUTH'] ?? '') . ' ';

		if (str_contains($methods, ' PLAIN '))
		{
			$this->Command($socket, 'AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), [235]);
			return;
		}

		if (str_contains($methods, ' LOGIN '))
		{
			$this->Command($socket, 'AUTH LOGIN', [334]);
			$this->Command($socket, base64_encode($username), [334]);
			$this->Command($socket, base64_encode($password), [235]);
			return;
		}

		throw new MailTransportException('The SMTP server offers no supported authentication method.');
	}

	/**
	 * @brief Builds an RFC-compatible UTF-8 multipart/alternative message.
	 *
	 * The queue stores immutable Markdown source. Delivery derives both a readable
	 * plain-text alternative and conservative inline-styled HTML from that source.
	 */
	private function BuildMessage(string $recipientEmail, string $subject, string $bodyText): string
	{
		$fromAddress = (string)$this->_config['from_address'];
		$fromName = $this->EncodeHeader((string)$this->_config['from_name']);
		$boundary = 'pulse-alt-' . bin2hex(random_bytes(18));
		$plainBody = $this->NormalizeBody($this->_markdownRenderer->ToPlainText($bodyText));
		$htmlBody = $this->NormalizeBody($this->_markdownRenderer->ToEmailHtml($bodyText));
		$message = implode("\r\n", [
			'Date: ' . gmdate('D, d M Y H:i:s O'),
			'From: ' . $fromName . ' <' . $fromAddress . '>',
			'To: <' . $recipientEmail . '>',
			'Subject: ' . $this->EncodeHeader($subject),
			'MIME-Version: 1.0',
			'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
			'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->MessageIdHost() . '>',
			'',
			'--' . $boundary,
			'Content-Type: text/plain; charset=UTF-8',
			'Content-Transfer-Encoding: quoted-printable',
			'',
			quoted_printable_encode($plainBody),
			'--' . $boundary,
			'Content-Type: text/html; charset=UTF-8',
			'Content-Transfer-Encoding: quoted-printable',
			'',
			quoted_printable_encode($htmlBody),
			'--' . $boundary . '--',
		]);

		return preg_replace('/(?m)^\./', '..', $message) ?? $message;
	}

	/** @brief Normalizes MIME body line endings to CRLF before transfer encoding. */
	private function NormalizeBody(string $body): string
	{
		$body = str_replace(["\r\n", "\r"], "\n", $body);
		return str_replace("\n", "\r\n", $body);
	}

	/** @brief Encodes a non-ASCII header value. */
	private function EncodeHeader(string $value): string
	{
		$this->AssertHeaderValue($value, 'mail header');
		return preg_match('/^[\x20-\x7E]*$/', $value) === 1
			? $value
			: '=?UTF-8?B?' . base64_encode($value) . '?=';
	}

	/** @brief Returns a safe Message-ID host. */
	private function MessageIdHost(): string
	{
		$host = (string)(parse_url((string)($this->_config['base_url'] ?? ''), PHP_URL_HOST) ?? '');
		return preg_match('/^[a-z0-9.-]+$/i', $host) === 1 ? $host : 'pulse.local';
	}

	/** @brief Rejects CR/LF header injection. */
	private function AssertHeaderValue(string $value, string $label): void
	{
		if (str_contains($value, "\r") || str_contains($value, "\n"))
		{
			throw new MailTransportException('Invalid ' . $label . '.');
		}
	}

	/**
	 * @brief Writes one SMTP command and validates its response.
	 * @param resource $socket SMTP socket.
	 * @param array<int> $expectedCodes Accepted codes.
	 */
	private function Command($socket, string $command, array $expectedCodes): string
	{
		$this->Write($socket, $command . "\r\n");
		return $this->Expect($socket, $expectedCodes);
	}

	/** @brief Writes all bytes or throws a transport failure. @param resource $socket SMTP socket. */
	private function Write($socket, string $data): void
	{
		$remaining = $data;

		while ($remaining !== '')
		{
			$written = @fwrite($socket, $remaining);

			if ($written === false || $written === 0)
			{
				throw new MailTransportException('The SMTP connection closed while sending data.');
			}

			$remaining = substr($remaining, $written);
		}
	}

	/**
	 * @brief Reads one potentially multi-line SMTP response.
	 * @param resource $socket SMTP socket.
	 * @param array<int> $expectedCodes Accepted codes.
	 */
	private function Expect($socket, array $expectedCodes): string
	{
		$lines = [];
		$code = 0;

		do
		{
			$line = @fgets($socket, 8192);

			if (!is_string($line))
			{
				throw new MailTransportException('The SMTP server did not return a complete response.');
			}

			$lines[] = rtrim($line, "\r\n");
			$code = (int)substr($line, 0, 3);
			$continued = isset($line[3]) && $line[3] === '-';
		}
		while ($continued);

		$response = implode("\n", $lines);

		if (!in_array($code, $expectedCodes, true))
		{
			$detail = $this->SanitizeServerResponse($response);
			$message = 'SMTP rejected the operation with status ' . $code . '.';

			if ($detail !== '')
			{
				$message .= ' Server response: ' . $detail;
			}

			throw new MailTransportException($message);
		}

		return $response;
	}

	/**
	 * @brief Converts an SMTP server response into bounded log/UI-safe diagnostic text.
	 * @param string $response Raw SMTP response.
	 * @return string
	 */
	private function SanitizeServerResponse(string $response): string
	{
		$singleLine = preg_replace('/[\r\n\t]+/', ' ', $response) ?? '';
		$printable = preg_replace('/[^\x20-\x7E\x80-\xFF]/', '', $singleLine) ?? '';
		$normalized = trim(preg_replace('/\s+/', ' ', $printable) ?? '');

		return strlen($normalized) > 500 ? substr($normalized, 0, 500) . '…' : $normalized;
	}
}
