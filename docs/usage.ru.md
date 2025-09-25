# SDK Gamma Generate — Руководство по использованию (Русский)

Документ описывает установку, конфигурацию и расширение неофициального SDK Gamma Generate для PHP (`venq/gamma-app-php-sdk`). SDK поддерживает PHP 8.4+, реализует стандарты PSR и инкапсулирует вызовы `POST /v0.2/generations` и `GET /v0.2/generations/{generationId}`.

## 1. Установка

```bash
composer require venq/gamma-app-php-sdk
```

Требования:

- PHP 8.4 или выше с `ext-json`
- PSR-18 HTTP-клиент и PSR-17 фабрика запросов (Guzzle 7/8 подходит из коробки)
- PSR-3 логгер (необязательно, по умолчанию используется `NullLogger`)

## 2. Быстрый старт

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
    ->withInputText("# План запуска\nВступление\n---\nЭтапы")
    ->withFormat(Format::Presentation)
    ->withTextMode(TextMode::Generate)
    ->withNumCards(12)
    ->withCardSplit(CardSplit::Auto)
    ->withTextOptions([
        'language' => Language::RU,
        'tone' => 'уверенно, лаконично',
        'audience' => 'продуктовая команда',
    ])
    ->withImageOptions([
        'source' => 'aiGenerated',
        'model' => ImageModel::IMAGEN_4_PRO,
        'style' => 'фотореалистично',
    ])
    ->withExportAs('pdf');

$response = $client->createGeneration($request);

$poller = new PollingClient($client);
$result = $poller->waitUntilCompleted($response->generationId, intervalSeconds: 5, timeoutSeconds: 240);

echo $result->gammaUrl . PHP_EOL;
```

## 3. Конфигурация (`ClientConfig`)

| Параметр | Тип | Значение по умолчанию | Назначение |
| --- | --- | --- | --- |
| `baseUrl` | string | `https://public-api.gamma.app` | Базовый домен API. Изменяется только в тестах.
| `apiVersion` | string | `v0.2` | Версия API, добавляется ко всем путям.
| `apiKey` | ?string | `null` | Ключ для заголовка `X-API-KEY`. Читается из `GAMMA_API_KEY` при `fromEnv()`.
| `timeout` | float | `30.0` | Тайм-аут запроса (секунды).
| `connectTimeout` | float | `5.0` | Тайм-аут подключения (секунды).
| `retries` | int | `2` | Максимум повторов для GET-запросов. POST не повторяется.
| `retryBackoff` | callable | экспоненциальный backoff с jitter | Принимает номер попытки, возвращает задержку в секундах.
| `defaultHeaders` | array<string,string> | `[]` | Дополнительные заголовки для всех запросов.
| `userAgent` | string | `GammaSDK-PHP/1.0 (+https://github.com/venq/gamma-app-php-sdk)` | Используется, если пользовательский `User-Agent` не задан.

Полезные методы:

- `ClientConfig::fromEnv()` — подтягивает ключ из переменной окружения.
- `ClientConfig::fromArray()` — точечная настройка параметров.
- `ClientConfig::buildHeaders()` — собирает итоговый набор заголовков с учётом `defaultHeaders` и `additional`.

## 4. Интеграция HTTP-клиента

По умолчанию `GammaClient::createDefault()` использует Guzzle и настраивает тайм-ауты. Можно подменить реализацию PSR-18:

```php
$client = new GammaClient(
    config: $config,
    httpClient: new CustomPsr18Client(),
    requestFactory: new CustomRequestFactory(),
    logger: $logger,
);
```

Поведение повторов:

- GET повторяется при `429` (с учётом `Retry-After`) и `5xx`.
- POST всегда возвращает первую ошибку.

#### Помощники для параметров запроса

`CreateGenerationRequest` содержит цепочные методы для вложенных опций, которые Gamma ждёт в теле запроса.

##### Text options (`withTextOptions(array $options)`)

| Ключ | Тип | Допустимые значения | Назначение |
| --- | --- | --- | --- |
| `amount` | string | `concise`, `balanced`, `detailed` | Объём генерируемого текста: от краткого до подробного. |
| `tone` | string | свободная строка (через запятую) | Тональность, например "friendly, confident". |
| `audience` | string | свободная строка | Уточняет целевую аудиторию (например, "инвесторы"). |
| `language` | string или enum `Language` | см. [enum Language](#104-language) | Принудительно задаёт язык результата. |

Все поля необязательны — Gamma подставляет стандартные значения, если они не переданы.

##### Image options (`withImageOptions(array $options)`)

| Ключ | Тип | Допустимые значения | Назначение |
| --- | --- | --- | --- |
| `source` | string | `aiGenerated`, `unsplash`, `uploaded` | Источник изображений: генерация ИИ, стоки Unsplash или загруженные ассеты. |
| `model` | string или enum `ImageModel` | см. [enum ImageModel](#105-imagemodel) | Определяет модель при `source = aiGenerated`. |
| `style` | string | свободная строка | Общий художественный стиль ("минималистичный", "фотореалистичный" и т. п.). |
| `prompt` | string | свободная строка | Дополнительное описание для генерации изображений. |

Дополнительные поля (`assetIds`, `dimensions` и др.) передаются в API без изменений.

##### Export options (`withExportAs(?string $format)`)

Принимает `pdf` или `pptx`. Без параметра Gamma создаёт только веб-версию (`gammaUrl`). После экспорта `GeneratedGamma` содержит `pdfUrl`, `pptxUrl` и массив `exportUrls`.

Актуальный список параметров доступен в документации Gamma: <https://developers.gamma.app/docs/how-does-the-generations-api-work>.

## 5. Методы `GammaClient`

### `createGeneration(CreateGenerationRequest $request)`

Отправляет генерацию. Возвращает DTO `CreateGenerationResponse` с `generationId` и массивом предупреждений.

### `getGeneration(string $generationId)`

Возвращает `PendingGeneration` (ожидание) или `GeneratedGamma` (готовый результат).

## 6. `PollingClient`

`waitUntilCompleted()` циклично вызывает `getGeneration`, обрабатывая лимиты и 5xx. Тайм-аут контролируется параметром `timeoutSeconds`.

## 7. Обработка ошибок

| Статус | Исключение | Пояснение |
| --- | --- | --- |
| 400 | `BadRequestException` | Неверный запрос.
| 401 | `UnauthorizedException` | Неверный или отсутствующий ключ.
| 403 | `ForbiddenException` | Нет доступа/кредитов.
| 404 | `NotFoundException` | Генерация не найдена.
| 422 | `UnprocessableException` | Невозможно обработать данные.
| 429 | `TooManyRequestsException` | Превышение лимитов, включает `Retry-After`.
| 5xx | `ServerException` | Ошибка на стороне Gamma.

Все исключения наследуются от `GammaException` и предоставляют методы `getStatusCode()`, `getError()`, `getResponse()`.

## 8. Предупреждения Gamma

Поле `warnings` отображается даже при успешном ответе (например, использование beta-функций). SDK логирует предупреждения и передаёт их в DTO.

## 9. Тестирование

- Юнит-тесты: `vendor/bin/phpunit --coverage-text`
- Статический анализ: `vendor/bin/phpstan analyse --level=max`

Для отчёта покрытия установите `pcov` или `xdebug`.

## 10. Перечень enum-ов

### 10.1 `Format`

| Значение enum | JSON | Описание |
| --- | --- | --- |
| `Format::Presentation` | `presentation` | Презентация/дек слайдов.
| `Format::Document` | `document` | Документ с длинным текстом.
| `Format::Social` | `social` | Короткие карточки для соцсетей.

### 10.2 `TextMode`

| Значение enum | JSON | Описание |
| --- | --- | --- |
| `TextMode::Generate` | `generate` | Генерация нового текста по структуре.
| `TextMode::Condense` | `condense` | Сжатое изложение входных данных.
| `TextMode::Preserve` | `preserve` | Максимально сохраняет исходный текст.

### 10.3 `CardSplit`

| Значение enum | JSON | Описание |
| --- | --- | --- |
| `CardSplit::Auto` | `auto` | Разбиение определяется автоматически Gamma.
| `CardSplit::InputTextBreaks` | `inputTextBreaks` | Разделитель `---` в `inputText` формирует границы карточек.

### 10.4 `Language`

| Значение enum | JSON | Язык |
| --- | --- | --- |
| `Language::EN` | `en` | Английский (США).
| `Language::EN_GB` | `en-GB` | Английский (Великобритания).
| `Language::ES` | `es` | Испанский.
| `Language::FR` | `fr` | Французский.
| `Language::DE` | `de` | Немецкий.
| `Language::IT` | `it` | Итальянский.
| `Language::PT` | `pt` | Португальский (Португалия).
| `Language::PT_BR` | `pt-BR` | Португальский (Бразилия).
| `Language::RU` | `ru` | Русский.
| `Language::JA` | `ja` | Японский.
| `Language::KO` | `ko` | Корейский.
| `Language::ZH_CN` | `zh-CN` | Китайский (упрощённый).
| `Language::ZH_TW` | `zh-TW` | Китайский (традиционный).
| `Language::HI` | `hi` | Хинди.
| `Language::AR` | `ar` | Арабский.
| `Language::NL` | `nl` | Нидерландский.
| `Language::SV` | `sv` | Шведский.
| `Language::NO` | `no` | Норвежский.
| `Language::DA` | `da` | Датский.
| `Language::FI` | `fi` | Финский.
| `Language::PL` | `pl` | Польский.
| `Language::TR` | `tr` | Турецкий.
| `Language::CS` | `cs` | Чешский.
| `Language::EL` | `el` | Греческий.
| `Language::HE` | `he` | Иврит.
| `Language::ID` | `id` | Индонезийский.
| `Language::MS` | `ms` | Малайский.
| `Language::TH` | `th` | Тайский.
| `Language::VI` | `vi` | Вьетнамский.
| `Language::UK` | `uk` | Украинский.
| `Language::RO` | `ro` | Румынский.
| `Language::HU` | `hu` | Венгерский.
| `Language::BG` | `bg` | Болгарский.
| `Language::SR` | `sr` | Сербский.
| `Language::HR` | `hr` | Хорватский.
| `Language::SK` | `sk` | Словацкий.

### 10.5 `ImageModel`

| Значение enum | JSON | Комментарий |
| --- | --- | --- |
| `ImageModel::IMAGEN_4_PRO` | `imagen-4-pro` | Imagen 4 Pro (максимальное качество).
| `ImageModel::IMAGEN_4` | `imagen-4` | Imagen 4 базовый.
| `ImageModel::IMAGEN_4_LIGHTNING` | `imagen-4-lightning` | Быстрая версия Imagen 4.
| `ImageModel::IMAGEN_3` | `imagen-3` | Imagen 3 стандарт.
| `ImageModel::IMAGEN_3_LITE` | `imagen-3-lite` | Облегчённый Imagen 3.
| `ImageModel::IMAGEN_3_ULTRA` | `imagen-3-ultra` | Максимальное качество Imagen 3.
| `ImageModel::IMAGEN_3_LIGHTNING` | `imagen-3-lightning` | Быстрый Imagen 3.
| `ImageModel::IMAGEN_2` | `imagen-2` | Предыдущее поколение Imagen 2.
| `ImageModel::FLUX_STANDARD` | `flux-standard` | Базовая модель Flux.
| `ImageModel::FLUX_PRO` | `flux-pro` | Улучшенная модель Flux.
| `ImageModel::FLUX_ULTRA` | `flux-ultra` | Flux высокого качества (портрет/альбом).
| `ImageModel::FLUX_ULTRA_SQUARE` | `flux-ultra-square` | Flux высокого качества для квадратных кадров.

## 11. Интеграция с Laravel

- `examples/laravel_service_provider.php` — регистрация клиента Gamma в контейнере.
- `examples/laravel_controller_usage.php` — пример контроллера с polling.

Добавьте переменные окружения `GAMMA_API_KEY`, при необходимости `GAMMA_WORKSPACE_ID`.

## 12. Типичные проблемы

| Симптом | Решение |
| --- | --- |
| Частые `TooManyRequestsException` | Увеличьте интервал опроса, обрабатывайте `Retry-After`, вынесите генерации в очередь. |
| `ServerException` при `getGeneration` | Используйте `PollingClient`, дождитесь стабилизации сервиса. |
| В `GeneratedGamma` нет ссылок на экспорт | Проверьте, передан ли `exportAs`, изучите `exportUrls`. |
| PHPUnit не выводит покрытие | Установите `pcov` или `xdebug`. |

Справка по API: [Gamma Generate Documentation](https://developers.gamma.app/docs).

