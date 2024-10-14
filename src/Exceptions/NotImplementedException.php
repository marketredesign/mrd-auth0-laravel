<?php

namespace Marketredesign\MrdAuth0Laravel\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class NotImplementedException extends HttpException
{
    public function __construct(
        string $message = '',
        int $statusCode = 503,
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }
}
