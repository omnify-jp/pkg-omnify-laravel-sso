<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Exceptions;

class ConsoleAccessDeniedException extends ConsoleApiException
{
    public function __construct(string $message = 'Access denied', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, 'ACCESS_DENIED', $previous);
    }
}
