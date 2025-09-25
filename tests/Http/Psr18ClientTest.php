<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Http;

use Gamma\SDK\Http\Psr18Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr18ClientTest extends TestCase
{
    public function testDelegatesToProvidedClient(): void
    {
        $expected = new Response(200);
        $inner = new class($expected) implements PsrClientInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $client = new Psr18Client($inner);
        $result = $client->sendRequest(new Request('GET', 'https://example.test'));

        self::assertSame($expected, $result);
    }
}
