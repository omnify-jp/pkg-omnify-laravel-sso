<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Exceptions;

class ConsoleNotFoundException extends ConsoleApiException
{
    public function __construct(string $message = 'Resource not found', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, 'NOT_FOUND', $previous);
    }
}
