<?php

declare(strict_types=1);

namespace Gamma\SDK\Exceptions;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class GammaException extends RuntimeException
{
    /**
     * @param array<string, mixed>|null $error
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly ?array $error = null,
        private readonly ?ResponseInterface $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getError(): ?array
    {
        return $this->error;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
