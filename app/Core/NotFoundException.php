<?php

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Exception used for HTTP 404 / route not found situations.
 */
class NotFoundException extends RuntimeException
{
}