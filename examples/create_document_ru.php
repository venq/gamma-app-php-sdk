<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Gamma\SDK\Contracts\CreateGenerationRequest;
use Gamma\SDK\Enums\Format;
use Gamma\SDK\Enums\Language;
use Gamma\SDK\Enums\TextMode;
use Gamma\SDK\GammaClient;

$client = GammaClient::createDefault(apiKey: getenv('GAMMA_API_KEY') ?: null);

$request = (new CreateGenerationRequest())
    ->withInputText("# Руководство по продукту\nВведение\n---\nОсновные функции\n---\nЧастые вопросы")
    ->withFormat(Format::Document)
    ->withTextMode(TextMode::Condense)
    ->withTextOptions([
        'amount' => 'concise',
        'tone' => 'дружелюбный, профессиональный',
        'audience' => 'новые пользователи SaaS',
        'language' => Language::RU,
    ])
    ->withAdditionalInstructions('Добавь список шагов и выдели предупреждения жирным');

$response = $client->createGeneration($request);

echo 'Документ создаётся. ID: ' . $response->generationId . PHP_EOL;
