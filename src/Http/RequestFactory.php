<?php

declare(strict_types=1);

namespace Gamma\SDK\Http;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

final class RequestFactory
{
    /**
     * @param array<string, string> $headers
     */
    public function create(string $method, string $uri, array $headers = [], ?string $body = null): RequestInterface
    {
        return new Request($method, $uri, $headers, $body ?? '');
    }
}
