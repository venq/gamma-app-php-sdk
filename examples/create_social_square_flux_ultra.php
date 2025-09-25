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
    ->withInputText("# Social Media Carousel\nAnnouncement\n---\nKey Benefits\n---\nCall to action")
    ->withFormat(Format::Social)
    ->withTextMode(TextMode::Preserve)
    ->withCardSplit(CardSplit::InputTextBreaks)
    ->withCardOptions(['dimensions' => '4x3'])
    ->withTextOptions([
        'amount' => 'punchy',
        'tone' => 'energetic, bold',
        'audience' => 'startup founders',
        'language' => Language::EN,
    ])
    ->withImageOptions([
        'source' => 'aiGenerated',
        'model' => ImageModel::FLUX_ULTRA,
        'style' => 'vibrant square artwork',
    ])
    ->withExportAs('pdf');

$response = $client->createGeneration($request);

echo 'Social carousel request queued. ID: ' . $response->generationId . PHP_EOL;
