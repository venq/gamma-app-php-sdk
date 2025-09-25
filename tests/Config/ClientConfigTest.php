<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Config;

use Gamma\SDK\Config\ClientConfig;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    public function testBuildHeadersIncludesDefaultsAndApiKey(): void
    {
        $config = new ClientConfig(apiKey: 'secret-key', defaultHeaders: ['Accept' => 'application/json+gamma']);

        $headers = $config->buildHeaders(['X-Custom' => 'value']);

        self::assertSame('application/json+gamma', $headers['Accept']);
        self::assertSame('application/json', $headers['Content-Type']);
        self::assertSame('GammaSDK-PHP/1.0 (+https://github.com/venq/gamma-app-php-sdk)', $headers['User-Agent']);
        self::assertSame('secret-key', $headers['X-API-KEY']);
        self::assertSame('value', $headers['X-Custom']);
    }

    public function testRetryBackoffProducesNonNegativeDelay(): void
    {
        $config = new ClientConfig();
        $backoff = $config->getRetryBackoff();

        self::assertGreaterThanOrEqual(0, $backoff(1));
        self::assertGreaterThanOrEqual(0, $backoff(2));
    }
}
