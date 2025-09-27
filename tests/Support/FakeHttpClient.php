<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Support;

use Gamma\SDK\Http\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class FakeHttpClient implements ClientInterface
{
    /** @var array<int, ResponseInterface|Throwable> */
    private array $queue = [];
    /** @var array<int, RequestInterface> */
    private array $requests = [];
    private ?ResponseInterface $lastResponse = null;

    public function __construct(ResponseInterface|Throwable ...$items)
    {
        $this->queue = array_values($items);
    }

    public function append(ResponseInterface|Throwable $item): void
    {
        $this->queue[] = $item;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->queue === []) {
            if ($this->lastResponse !== null) {
                return $this->lastResponse;
            }

            throw new RuntimeException('FakeHttpClient queue is empty.');
        }

        $item = array_shift($this->queue);
        if ($item instanceof Throwable) {
            if ($item instanceof ClientExceptionInterface) {
                throw $item;
            }

            throw new class ($item) extends RuntimeException implements ClientExceptionInterface {
                public function __construct(private readonly Throwable $previousThrowable)
                {
                    parent::__construct($previousThrowable->getMessage(), 0, $previousThrowable);
                }

                public function getWrapped(): Throwable
                {
                    return $this->previousThrowable;
                }
            };
        }

        $this->lastResponse = $item;

        return $item;
    }

    /**
     * @return array<int, RequestInterface>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }
}
