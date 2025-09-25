# Gamma Generate SDK for PHP (Unofficial, gamma-app-php-sdk)

Production-ready PHP 8.4 client for the Gamma Generate API v0.2. The SDK wraps the `POST /v0.2/generations` and `GET /v0.2/generations/{generationId}` endpoints with PSR-compliant abstractions, retries, and helpful DTOs.
> **Note:** Community-maintained by venq as gamma-app-php-sdk; this is not an official Gamma SDK.

## Installation

```bash
composer require venq/gamma-app-php-sdk
```

The package targets PHP 8.4+ and relies on PSR-18/PSR-17 compliant HTTP clients. A tuned Guzzle adapter is bundled for convenience.

## Getting Started

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Gamma\SDK\Contracts\CreateGenerationRequest;
use Gamma\SDK\Enums\CardSplit;
use Gamma\SDK\Enums\Format;
use Gamma\SDK\Enums\ImageModel;
use Gamma\SDK\Enums\Language;
use Gamma\SDK\Enums\TextMode;
use Gamma\SDK\GammaClient;
use Gamma\SDK\PollingClient;

$client = GammaClient::createDefault(apiKey: getenv('GAMMA_API_KEY'));

$request = (new CreateGenerationRequest())
    ->withInputText("# Launch Deck\nIntro\n---\nRoadmap")
    ->withFormat(Format::Presentation)
    ->withTextMode(TextMode::Generate)
    ->withNumCards(10)
    ->withCardSplit(CardSplit::Auto)
    ->withTextOptions([
        'amount' => 'detailed',
        'tone' => 'professional, inspiring',
        'audience' => 'SaaS founders',
        'language' => Language::EN,
    ])
    ->withImageOptions([
        'source' => 'aiGenerated',
        'model' => ImageModel::IMAGEN_4_PRO,
        'style' => 'photorealistic',
    ])
    ->withExportAs('pdf');

$response = $client->createGeneration($request);

$poller = new PollingClient($client);
$result = $poller->waitUntilCompleted($response->generationId, 5, 300);

echo $result->gammaUrl . PHP_EOL;
```

### Authentication

Set `GAMMA_API_KEY` in your environment or pass it explicitly to `GammaClient::createDefault()`. The SDK sends the value in the `X-API-KEY` header for every request.

### Warnings & Error Handling

Gamma may return non-blocking warnings alongside successful responses. The SDK surfaces them on DTOs and logs each warning through the provided PSR-3 logger (defaults to `NullLogger`).

HTTP errors are mapped to domain-specific exceptions:

- `400` → `BadRequestException`
- `401` → `UnauthorizedException`
- `403` → `ForbiddenException` (often means your workspace lacks credits)
- `404` → `NotFoundException`
- `422` → `UnprocessableException`
- `429` → `TooManyRequestsException` (includes `Retry-After` when present)
- `5xx` → `ServerException`

### Retries & Timeouts

- `ClientConfig` exposes `timeout`, `connectTimeout`, `retries`, and a jittered `retryBackoff` callback.
- Automatic retries apply only to idempotent `GET` requests. `POST` calls surface the first error instantly as recommended by Gamma.
- Use `PollingClient` to repeatedly fetch a generation with built-in backoff for `429` and `5xx` responses.

### Rate Limits & Access

Gamma is currently in beta and enforces workspace-level limits (50 generations per user per day). Request elevated access through [Gamma Generate API docs](https://developers.gamma.app/docs/get-access).

## Documentation

- [Usage guide (EN)](docs/usage.en.md)
- [Usage guide (RU)](docs/usage.ru.md)

## Examples

See the `/examples` directory for ready-to-run scripts:

- `create_presentation.php`
- `create_document_ru.php`
- `create_social_square_flux_ultra.php`

Each demonstrates a different combination of formats, languages, and image models.\r\n\r\nAdditional Laravel integration snippets live in /examples/laravel_service_provider.php and /examples/laravel_controller_usage.php.\r\n
## Testing & Quality

The repository ships with PHPUnit, PHPStan (max level), and property-style enum tests. To run the suite locally:

```bash
composer install
vendor/bin/phpunit --coverage-text
vendor/bin/phpstan analyse --level=max
```

## Continuous Integration

A GitHub Actions workflow (see `.github/workflows/ci.yml`) validates composer metadata, runs linting, executes tests with coverage (pcov), and enforces PHPStan level max on PHP 8.4.

## Links

- [Gamma Generate API Overview](https://developers.gamma.app/docs)
- [Generate a Gamma](https://developers.gamma.app/reference/generate-a-gamma)
- [Receive generated file URLs](https://developers.gamma.app/reference/get-gamma-file-urls)
- [Error Codes & Warnings](https://developers.gamma.app/reference/error-codes)




