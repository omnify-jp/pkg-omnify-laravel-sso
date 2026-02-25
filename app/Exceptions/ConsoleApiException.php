<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Exceptions;

use Exception;

class ConsoleApiException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?string $errorCode = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
