<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Exceptions;

class ConsoleServerException extends ConsoleApiException
{
    public function __construct(string $message = 'Console server error', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, 'SERVER_ERROR', $previous);
    }
}
