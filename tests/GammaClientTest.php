<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests;

use Gamma\SDK\Contracts\CreateGenerationRequest;
use Gamma\SDK\Contracts\GeneratedGamma;
use Gamma\SDK\Contracts\PendingGeneration;
use Gamma\SDK\Enums\Format;
use Gamma\SDK\Enums\TextMode;
use Gamma\SDK\Exceptions\NotFoundException;
use Gamma\SDK\Tests\Support\GammaTestCase;
use Gamma\SDK\Tests\Support\ResponseFactory;

final class GammaClientTest extends GammaTestCase
{
    public function testCreateGenerationReturnsResponseAndLogsWarnings(): void
    {
        $client = $this->createClient([
            ResponseFactory::json([
                'generationId' => 'gen-123',
                'warnings' => [
                    ['code' => 'beta', 'message' => 'Feature in beta'],
                ],
            ]),
        ]);

        $request = (new CreateGenerationRequest())
            ->withInputText('Hello')
            ->withFormat(Format::Presentation)
            ->withTextMode(TextMode::Generate);

        $response = $client['instance']->createGeneration($request);

        self::assertSame('gen-123', $response->generationId);
        self::assertCount(1, $response->warnings);
        self::assertNotEmpty($client['logger']->records);
    }

    public function testGetGenerationReturnsPending(): void
    {
        $client = $this->createClient([
            ResponseFactory::json([
                'status' => 'pending',
                'generationId' => 'gen-1',
                'estimatedWaitSeconds' => 12,
                'credits' => ['remaining' => 42],
            ]),
        ]);

        $result = $client['instance']->getGeneration('gen-1');

        self::assertInstanceOf(PendingGeneration::class, $result);
        self::assertSame('pending', $result->status);
        self::assertSame(12, $result->estimatedWaitSeconds);
        self::assertSame(['remaining' => 42], $result->credits);
    }

    public function testGetGenerationReturnsCompletedWithExports(): void
    {
        $client = $this->createClient([
            ResponseFactory::json([
                'status' => 'completed',
                'generationId' => 'gen-2',
                'gammaUrl' => 'https://gamma.app/g/gen-2',
                'exportUrls' => [
                    'pdf' => 'https://gamma.app/export/gen-2.pdf',
                ],
            ]),
        ]);

        $result = $client['instance']->getGeneration('gen-2');

        self::assertInstanceOf(GeneratedGamma::class, $result);
        self::assertSame('completed', $result->status);
        self::assertSame('https://gamma.app/g/gen-2', $result->gammaUrl);
        self::assertSame('https://gamma.app/export/gen-2.pdf', $result->pdfUrl);
    }

    public function testGetGenerationRetriesOnServerError(): void
    {
        $client = $this->createClient([
            ResponseFactory::json(['message' => 'Server error'], 500),
            ResponseFactory::json([
                'status' => 'pending',
                'generationId' => 'gen-3',
            ]),
        ]);

        $client['instance']->setSleeper(static function (int $seconds): void {
            // no-op for tests
        });

        $result = $client['instance']->getGeneration('gen-3');
        self::assertInstanceOf(PendingGeneration::class, $result);
        self::assertSame('pending', $result->status);
        self::assertCount(2, $client['http']->getRequests());
    }

    public function testGetGenerationThrowsMappedException(): void
    {
        $client = $this->createClient([
            ResponseFactory::json(['message' => 'Missing'], 404),
        ]);

        $this->expectException(NotFoundException::class);
        $client['instance']->getGeneration('missing');
    }
}
