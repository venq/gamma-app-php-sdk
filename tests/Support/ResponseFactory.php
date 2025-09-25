<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ResponseFactory
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): ResponseInterface
    {
        $headers = ['Content-Type' => 'application/json'] + $headers;
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode response payload for test fixture.');
        }

        return new Response(
            $status,
            $headers,
            $encoded,
        );
    }
}
