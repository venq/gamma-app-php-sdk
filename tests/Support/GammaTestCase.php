<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Support;

use Gamma\SDK\Config\ClientConfig;
use Gamma\SDK\GammaClient;
use Gamma\SDK\Http\RequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Throwable;

abstract class GammaTestCase extends TestCase
{
    /**
     * @param list<ResponseInterface|Throwable> $queue
     *
     * @return array{instance:GammaClient,http:FakeHttpClient,logger:ArrayLogger}
     */
    protected function createClient(array $queue): array
    {
        $http = new FakeHttpClient(...$queue);
        $logger = new ArrayLogger();
        $config = new ClientConfig(apiKey: 'test-key', retries: 2);
        $client = new GammaClient($config, $http, new RequestFactory(), $logger);

        return [
            'instance' => $client,
            'http' => $http,
            'logger' => $logger,
        ];
    }
}
