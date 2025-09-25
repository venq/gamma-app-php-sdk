<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamma\SDK\Contracts\CreateGenerationRequest;
use Gamma\SDK\Enums\Format;
use Gamma\SDK\Enums\Language;
use Gamma\SDK\Enums\TextMode;
use Gamma\SDK\GammaClient;
use Gamma\SDK\PollingClient;
use Illuminate\Http\JsonResponse;

final class GammaController
{
    public function __construct(private readonly GammaClient $client)
    {
    }

    public function createDeck(): JsonResponse
    {
        $request = (new CreateGenerationRequest())
            ->withInputText("# Sprint Review\nHighlights\n---\nMetrics")
            ->withFormat(Format::Presentation)
            ->withTextMode(TextMode::Generate)
            ->withTextOptions([
                'language' => Language::EN,
                'audience' => 'product stakeholders',
                'tone' => 'transparent, upbeat',
            ]);

        $response = $this->client->createGeneration($request);

        $poller = new PollingClient($this->client);
        $result = $poller->waitUntilCompleted($response->generationId, intervalSeconds: 4, timeoutSeconds: 180);

        return response()->json([
            'generationId' => $response->generationId,
            'gammaUrl' => $result->gammaUrl,
            'exportUrls' => $result->exportUrls,
        ]);
    }
}
