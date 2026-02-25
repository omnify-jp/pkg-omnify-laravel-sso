<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Exceptions;

class ConsoleAuthException extends ConsoleApiException
{
    public function __construct(string $message = 'Authentication failed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, 'AUTH_FAILED', $previous);
    }
}
