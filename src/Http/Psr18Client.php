<?php

declare(strict_types=1);

namespace Gamma\SDK\Http;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr18Client implements ClientInterface
{
    private PsrClientInterface $client;

    /**
     * @param array<string, mixed> $guzzleConfig
     */
    public function __construct(?PsrClientInterface $client = null, array $guzzleConfig = [])
    {
        if ($client !== null) {
            $this->client = $client;

            return;
        }

        $defaults = [
            'http_errors' => false,
        ];

        $this->client = new GuzzleClient($defaults + $guzzleConfig);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    public function getInnerClient(): PsrClientInterface
    {
        return $this->client;
    }
}
