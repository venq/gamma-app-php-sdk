<?php

declare(strict_types=1);

namespace Gamma\SDK\Exceptions;

final class TooManyRequestsException extends GammaException
{
    public function __construct(
        string $message = 'Too many requests',
        int $statusCode = 429,
        private readonly ?int $retryAfter = null,
        ?array $error = null,
        ?\Psr\Http\Message\ResponseInterface $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $error, $response, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
