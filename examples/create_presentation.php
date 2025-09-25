<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Gamma\SDK\Contracts\CreateGenerationRequest;
use Gamma\SDK\Enums\CardSplit;
use Gamma\SDK\Enums\Format;
use Gamma\SDK\Enums\ImageModel;
use Gamma\SDK\Enums\Language;
use Gamma\SDK\Enums\TextMode;
use Gamma\SDK\GammaClient;

$client = GammaClient::createDefault(apiKey: getenv('GAMMA_API_KEY') ?: null);

$request = (new CreateGenerationRequest())
    ->withInputText("# Product Launch Deck\nVision\n---\nRoadmap\n---\nPricing")
    ->withFormat(Format::Presentation)
    ->withTextMode(TextMode::Generate)
    ->withNumCards(12)
    ->withCardSplit(CardSplit::Auto)
    ->withTextOptions([
        'amount' => 'detailed',
        'tone' => 'confident, visionary',
        'audience' => 'executive stakeholders',
        'language' => Language::EN,
    ])
    ->withImageOptions([
        'source' => 'aiGenerated',
        'model' => ImageModel::IMAGEN_4_PRO,
        'style' => 'cinematic',
    ])
    ->withAdditionalInstructions('Highlight differentiation on every third slide')
    ->withExportAs('pdf');

$response = $client->createGeneration($request);

echo 'Generation ID: ' . $response->generationId . PHP_EOL;
