# Gamma Generate SDK — Usage Guide (English)

This document explains how to install, configure, and extend the unofficial Gamma Generate SDK for PHP (package `venq/gamma-app-php-sdk`). The SDK targets PHP 8.4+, follows PSR standards, and wraps Gamma’s `POST /v0.2/generations` and `GET /v0.2/generations/{generationId}` endpoints.

## 1. Installation

```bash
composer require venq/gamma-app-php-sdk
```

Requirements:

- PHP 8.4 or later with `ext-json`
- A PSR-18 HTTP client & PSR-17 request factory (Guzzle 7/8 works out of the box)
- PSR-3 logger (optional; defaults to `NullLogger`)

## 2. Quick Start

```php
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
    ->withInputText("# Launch Plan\nIntro\n---\nTimeline")
    ->withFormat(Format::Presentation)
    ->withTextMode(TextMode::Generate)
    ->withNumCards(12)
    ->withCardSplit(CardSplit::Auto)
    ->withTextOptions([
        'language' => Language::EN,
        'tone' => 'confident, concise',
        'audience' => 'product stakeholders',
    ])
    ->withImageOptions([
        'source' => 'aiGenerated',
        'model' => ImageModel::IMAGEN_4_PRO,
        'style' => 'cinematic',
    ])
    ->withExportAs('pdf');

$response = $client->createGeneration($request);

$poller = new PollingClient($client);
$result = $poller->waitUntilCompleted($response->generationId, intervalSeconds: 5, timeoutSeconds: 240);

echo $result->gammaUrl . PHP_EOL;
```

## 3. Configuration (`ClientConfig`)

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `baseUrl` | string | `https://public-api.gamma.app` | API host. Change only for testing.
| `apiVersion` | string | `v0.2` | Prepended to all path segments.
| `apiKey` | ?string | `null` | Used for `X-API-KEY`. Pulled from `GAMMA_API_KEY` by `ClientConfig::fromEnv()`.
| `timeout` | float | `30.0` | HTTP request timeout in seconds.
| `connectTimeout` | float | `5.0` | Connection timeout (seconds).
| `retries` | int | `2` | Maximum retry attempts for GET requests. POSTs never retry.
| `retryBackoff` | callable | jittered exponential | Receives attempt number (`1`-based) and returns delay in seconds.
| `defaultHeaders` | array<string,string> | `[]` | Additional headers applied to every request.
| `userAgent` | string | `GammaSDK-PHP/1.0 (+https://github.com/venq/gamma-app-php-sdk)` | Sent as `User-Agent` if none provided.

Helpers:

- `ClientConfig::fromEnv(?string $apiKey = null)` reads `GAMMA_API_KEY`.
- `ClientConfig::fromArray(array $data)` allows manual overrides.
- `ClientConfig::buildHeaders(array $additional = [])` merges defaults with runtime headers.

## 4. HTTP Client Integration

`GammaClient::createDefault()` wires Guzzle automatically. To provide your own PSR-18 implementation or tweak Guzzle options:

```php
$client = new GammaClient(
    config: $config,
    httpClient: new CustomPsr18Client(),
    requestFactory: new CustomRequestFactory(),
    logger: $logger,
);
```

Retry behaviour:

- GET requests retry on `429` (honouring `Retry-After`) and `5xx` responses, using the configured backoff.
- POST requests surface the first error immediately as per Gamma guidelines.

## 5. GammaClient API

### `createGeneration(CreateGenerationRequest $request): CreateGenerationResponse`

- Sends `POST /v0.2/generations`.
- Returns DTO containing `generationId` and `warnings` (non-fatal issues).
- Throws mapped exceptions on non-2xx.

#### Request option helpers

`CreateGenerationRequest` exposes fluent helpers for the nested option payloads that Gamma expects. These maps are serialized 1:1 into the JSON request body.

##### Text options (`withTextOptions(array $options)`)

| Key | Type | Accepted values | Purpose |
| --- | --- | --- | --- |
| `amount` | string | `concise`, `balanced`, `detailed` | Controls the density of generated copy. `concise` keeps slides lightweight; `detailed` produces more paragraphs. |
| `tone` | string | free-form descriptors (comma-separated) | Voice of the deck, e.g. "professional, inspiring". |
| `audience` | string | free-form | Helps Gamma tailor wording (e.g. "executive stakeholders"). |
| `language` | string or `Language` enum | see [Language enum](#104-language) | Forces output language regardless of detected input. |

Every field is optional. Unspecified keys fall back to Gamma defaults (`balanced` amount, neutral tone/audience inferred from topic).

##### Image options (`withImageOptions(array $options)`)

| Key | Type | Accepted values | Purpose |
| --- | --- | --- | --- |
| `source` | string | `aiGenerated`, `unsplash`, `uploaded` | Controls where image assets come from. `aiGenerated` lets Gamma call its image models; `unsplash` pulls stock; `uploaded` reuses assets you reference via `assetIds`. |
| `model` | string or `ImageModel` enum | see [ImageModel enum](#105-imagemodel) | Selects a specific AI art model when `source = aiGenerated`. |
| `style` | string | free-form | High-level art direction such as "minimalist", "photorealistic", "hand-drawn". |
| `prompt` | string | free-form | Optional extra description for AI image generation. |

Additional keys like `assetIds` (array of previously uploaded assets) or `dimensions` pass through untouched and will be forwarded to Gamma.

##### Export options (`withExportAs(?string $format)`)

Pass `pdf` or `pptx` to request Gamma’s export pipeline. When omitted the deck stays web-only (`gammaUrl` still returned). Successful exports surface URL fields on `GeneratedGamma`:

- `pdfUrl` when `exportAs('pdf')`
- `pptxUrl` when `exportAs('pptx')`
- `exportUrls` associative array for any additional formats Gamma enabled on your workspace

Refer to the Gamma documentation for the latest accepted values: <https://developers.gamma.app/docs/how-does-the-generations-api-work>.

### `getGeneration(string $generationId): PendingGeneration|GeneratedGamma`

- Fetches generation status.
- Returns `PendingGeneration` when still processing.
- Returns `GeneratedGamma` once completed, including export URLs.

## 6. PollingClient

`PollingClient::waitUntilCompleted($id, $intervalSeconds = 5, $timeoutSeconds = 300)` repeatedly calls `getGeneration` until a `GeneratedGamma` is returned or timeout expires. Handles throttling (429) and transient server errors internally.

## 7. Error Handling

HTTP status codes map to custom exceptions:

| Status | Exception | Meaning |
| --- | --- | --- |
| 400 | `BadRequestException` | Invalid payload or parameters. |
| 401 | `UnauthorizedException` | API key missing/invalid. |
| 403 | `ForbiddenException` | Workspace blocked or out of credits. |
| 404 | `NotFoundException` | Generation ID does not exist. |
| 422 | `UnprocessableException` | Validation succeeded but request cannot be processed. |
| 429 | `TooManyRequestsException` | Rate limit reached; includes optional `Retry-After`. |
| 5xx | `ServerException` | Gamma service-side error. |

Every exception extends `GammaException`, exposing:

- `getStatusCode()`
- `getError()` (decoded response payload, if any)
- `getResponse()` (original PSR-7 response)

## 8. Warnings

Successful responses may include `warnings` (e.g., feature in beta). The SDK logs each warning at `warning` level and surfaces them on DTOs (`CreateGenerationResponse::$warnings`, `PendingGeneration::$warnings`, `GeneratedGamma::$warnings`).

## 9. Testing & Quality

The repository includes:

- PHPUnit tests (`vendor/bin/phpunit --coverage-text`)
- PHPStan level max (`vendor/bin/phpstan analyse --level=max`)

To enable coverage locally install `pcov` or `xdebug`.

## 10. Enumerations

### 10.1 `Format`

| Enum Case | JSON Value | Description |
| --- | --- | --- |
| `Format::Presentation` | `presentation` | Multi-card deck optimized for Gamma slides. |
| `Format::Document` | `document` | Long-form document layout. |
| `Format::Social` | `social` | Short-form layouts for social posts/carousels. |

### 10.2 `TextMode`

| Enum Case | JSON Value | Description |
| --- | --- | --- |
| `TextMode::Generate` | `generate` | Produce new copy based on `inputText` outline. |
| `TextMode::Condense` | `condense` | Summarise/rewrite input into condensed format. |
| `TextMode::Preserve` | `preserve` | Keep original phrasing as much as possible. |

### 10.3 `CardSplit`

| Enum Case | JSON Value | Description |
| --- | --- | --- |
| `CardSplit::Auto` | `auto` | Let Gamma determine card boundaries. |
| `CardSplit::InputTextBreaks` | `inputTextBreaks` | Use `---` separators in `inputText` as hard breaks. |

### 10.4 `Language`

| Enum Case | JSON Value | Description |
| --- | --- | --- |
| `Language::EN` | `en` | English (US).
| `Language::EN_GB` | `en-GB` | English (UK).
| `Language::ES` | `es` | Spanish.
| `Language::FR` | `fr` | French.
| `Language::DE` | `de` | German.
| `Language::IT` | `it` | Italian.
| `Language::PT` | `pt` | Portuguese (Portugal).
| `Language::PT_BR` | `pt-BR` | Portuguese (Brazil).
| `Language::RU` | `ru` | Russian.
| `Language::JA` | `ja` | Japanese.
| `Language::KO` | `ko` | Korean.
| `Language::ZH_CN` | `zh-CN` | Chinese (Simplified).
| `Language::ZH_TW` | `zh-TW` | Chinese (Traditional).
| `Language::HI` | `hi` | Hindi.
| `Language::AR` | `ar` | Arabic.
| `Language::NL` | `nl` | Dutch.
| `Language::SV` | `sv` | Swedish.
| `Language::NO` | `no` | Norwegian.
| `Language::DA` | `da` | Danish.
| `Language::FI` | `fi` | Finnish.
| `Language::PL` | `pl` | Polish.
| `Language::TR` | `tr` | Turkish.
| `Language::CS` | `cs` | Czech.
| `Language::EL` | `el` | Greek.
| `Language::HE` | `he` | Hebrew.
| `Language::ID` | `id` | Indonesian.
| `Language::MS` | `ms` | Malay.
| `Language::TH` | `th` | Thai.
| `Language::VI` | `vi` | Vietnamese.
| `Language::UK` | `uk` | Ukrainian.
| `Language::RO` | `ro` | Romanian.
| `Language::HU` | `hu` | Hungarian.
| `Language::BG` | `bg` | Bulgarian.
| `Language::SR` | `sr` | Serbian.
| `Language::HR` | `hr` | Croatian.
| `Language::SK` | `sk` | Slovak.

### 10.5 `ImageModel`

| Enum Case | JSON Value | Description |
| --- | --- | --- |
| `ImageModel::IMAGEN_4_PRO` | `imagen-4-pro` | High-quality Google Imagen 4 Pro.
| `ImageModel::IMAGEN_4` | `imagen-4` | Imagen 4 standard.
| `ImageModel::IMAGEN_4_LIGHTNING` | `imagen-4-lightning` | Faster Imagen 4 variant.
| `ImageModel::IMAGEN_3` | `imagen-3` | Imagen 3 base model.
| `ImageModel::IMAGEN_3_LITE` | `imagen-3-lite` | Lightweight Imagen 3.
| `ImageModel::IMAGEN_3_ULTRA` | `imagen-3-ultra` | Maximum quality Imagen 3.
| `ImageModel::IMAGEN_3_LIGHTNING` | `imagen-3-lightning` | Fast Imagen 3.
| `ImageModel::IMAGEN_2` | `imagen-2` | Legacy Imagen 2.
| `ImageModel::FLUX_STANDARD` | `flux-standard` | Flux base diffusion.
| `ImageModel::FLUX_PRO` | `flux-pro` | Flux professional.
| `ImageModel::FLUX_ULTRA` | `flux-ultra` | Flux high quality (portrait/landscape).
| `ImageModel::FLUX_ULTRA_SQUARE` | `flux-ultra-square` | Flux high quality optimized for square outputs.

## 11. Laravel Integration

Two example scaffolds live under `/examples`:

- `laravel_service_provider.php` — registers a shared `GammaClient` in the container.
- `laravel_controller_usage.php` — demonstrates dependency injection and polling within a controller.

Adjust namespaces to match your Laravel app and add environment variables (`GAMMA_API_KEY`, optional `GAMMA_WORKSPACE_ID`).

## 12. Troubleshooting Tips

| Symptom | Resolution |
| --- | --- |
| `TooManyRequestsException` repeatedly thrown | Increase `retries`/`intervalSeconds`, respect `Retry-After`, consider queueing background jobs. |
| `ServerException` with 500-series codes | Retry via `PollingClient`, check Gamma status page. |
| `Null` export URLs | Ensure `exportAs` is set in request or inspect `GeneratedGamma::$exportUrls` for available formats. |
| No coverage report from PHPUnit | Install `pcov` or `xdebug` and rerun tests. |

For further API reference: [Gamma Generate API Docs](https://developers.gamma.app/docs).

