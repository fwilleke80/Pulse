<?php

/**
 * @file MailTransportInterface.php
 * @brief Transport boundary for delivering queued plain-text messages.
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
	 * @brief Delivers a plain-text email message.
	 * @param string $recipientEmail Recipient address.
	 * @param string $subject Message subject.
	 * @param string $bodyText UTF-8 plain-text body.
	 * @throws MailTransportException When delivery fails.
	 */
	public function Send(string $recipientEmail, string $subject, string $bodyText): void;
}
