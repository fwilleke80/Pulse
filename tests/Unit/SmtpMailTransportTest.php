<?php

/**
 * @file SmtpMailTransportTest.php
 * @brief Tests SMTP header-injection protection before network I/O.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Mail\MailTransportException;
use Pulse\Mail\SmtpMailTransport;

class SmtpMailTransportTest extends TestCase
{
	public function testRecipientHeaderInjectionIsRejectedBeforeConnecting(): void
	{
		$transport = new SmtpMailTransport([
			'host' => 'smtp.example.com',
			'port' => 587,
			'encryption' => 'starttls',
			'timeout_seconds' => 2,
			'username' => '',
			'password' => '',
			'from_address' => 'pulse@example.com',
			'from_name' => 'Pulse',
			'base_url' => 'https://pulse.example.com',
		]);

		$this->expectException(MailTransportException::class);
		$transport->Send("owner@example.com\r\nBcc: attacker@example.com", 'Test', 'Body');
	}
	public function testBuildMessageCreatesMultipartMarkdownAlternatives(): void
	{
		$transport = new SmtpMailTransport([
			'host' => 'smtp.example.com',
			'port' => 587,
			'encryption' => 'starttls',
			'timeout_seconds' => 2,
			'username' => '',
			'password' => '',
			'from_address' => 'pulse@example.com',
			'from_name' => 'Pulse',
			'base_url' => 'https://pulse.example.com',
		]);
		$method = new \ReflectionMethod($transport, 'BuildMessage');
		$message = (string)$method->invoke($transport, 'recipient@example.com', 'Test', "Hello **there**.\n\n[Open Pulse](https://pulse.example.com)");

		self::assertStringContainsString('Content-Type: multipart/alternative;', $message);
		self::assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $message);
		self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $message);
		self::assertStringContainsString(quoted_printable_encode('Hello there.'), $message);
		self::assertStringContainsString(quoted_printable_encode('<strong>there</strong>'), $message);
	}

}
