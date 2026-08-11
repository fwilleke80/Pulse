<?php

/**
 * @file MailTransportException.php
 * @brief Safe SMTP transport failure.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Mail;

use RuntimeException;

/**
 * @brief Represents a delivery failure without exposing SMTP credentials.
 */
final class MailTransportException extends RuntimeException
{
}
