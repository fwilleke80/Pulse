<?php

/**
 * @file MailTransportInterface.php
 * @brief Transport boundary for delivering queued Markdown-capable messages.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Mail;

/**
 * @brief Sends one immutable queued message.
 */
interface MailTransportInterface
{
	/**
	 * @brief Delivers a Markdown-capable email message with a plain-text fallback.
	 * @param string $recipientEmail Recipient address.
	 * @param string $subject Message subject.
	 * @param string $bodyText UTF-8 Markdown-capable source body.
	 * @throws MailTransportException When delivery fails.
	 */
	public function Send(string $recipientEmail, string $subject, string $bodyText): void;
}
