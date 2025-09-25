<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests;

use Gamma\SDK\Exceptions\GammaException;
use Gamma\SDK\PollingClient;
use Gamma\SDK\Tests\Support\GammaTestCase;
use Gamma\SDK\Tests\Support\ResponseFactory;

final class PollingClientTest extends GammaTestCase
{
    public function testWaitUntilCompletedReturnsGeneratedGamma(): void
    {
        $client = $this->createClient([
            ResponseFactory::json([
                'status' => 'pending',
                'generationId' => 'gen-10',
            ]),
            ResponseFactory::json([
                'status' => 'completed',
                'generationId' => 'gen-10',
                'gammaUrl' => 'https://gamma.app/g/gen-10',
            ]),
        ]);

        $client['instance']->setSleeper(static function (int $seconds): void {
            // suppress retry sleep
        });

        $poller = new PollingClient($client['instance'], $client['logger']);

        $result = $poller->waitUntilCompleted('gen-10', 1, 10);

        self::assertSame('completed', $result->status);
    }

    public function testWaitUntilCompletedTimesOut(): void
    {
        $client = $this->createClient([
            ResponseFactory::json([
                'status' => 'pending',
                'generationId' => 'gen-11',
            ]),
        ]);

        $poller = new PollingClient($client['instance'], $client['logger']);

        $this->expectException(GammaException::class);
        $poller->waitUntilCompleted('gen-11', 1, 1);
    }
}
