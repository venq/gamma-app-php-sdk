<?php

declare(strict_types=1);

namespace Gamma\SDK;

use Gamma\SDK\Config\ClientConfig;
use Gamma\SDK\Contracts\CreateGenerationRequest;
use Gamma\SDK\Contracts\CreateGenerationResponse;
use Gamma\SDK\Contracts\GeneratedGamma;
use Gamma\SDK\Contracts\PendingGeneration;
use Gamma\SDK\Exceptions\BadRequestException;
use Gamma\SDK\Exceptions\ForbiddenException;
use Gamma\SDK\Exceptions\GammaException;
use Gamma\SDK\Exceptions\NotFoundException;
use Gamma\SDK\Exceptions\ServerException;
use Gamma\SDK\Exceptions\TooManyRequestsException;
use Gamma\SDK\Exceptions\UnauthorizedException;
use Gamma\SDK\Exceptions\UnprocessableException;
use Gamma\SDK\Http\ClientInterface;
use Gamma\SDK\Http\Psr18Client;
use Gamma\SDK\Http\RequestFactory;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function assert;
use function ctype_digit;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function rtrim;
use function sleep;
use function sprintf;
use function strtotime;
use function time;

final class GammaClient
{
    private ClientConfig $config;
    private ClientInterface $httpClient;
    private RequestFactory $requestFactory;
    private LoggerInterface $logger;
    /** @var callable(int):void */
    private $sleeper;

    public function __construct(
        ClientConfig $config,
        ClientInterface $httpClient,
        RequestFactory $requestFactory,
        ?LoggerInterface $logger = null,
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->logger = $logger ?? new NullLogger();
        $this->sleeper = static function (int $seconds): void {
            if ($seconds > 0) {
                sleep($seconds);
            }
        };

        if (!$this->config->hasApiKey()) {
            $this->logger->warning('Gamma SDK initialized without an API key. Requests will likely fail with 401.');
        }
    }

    public static function createDefault(
        ?string $apiKey = null,
        ?ClientConfig $config = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactory $requestFactory = null,
        ?LoggerInterface $logger = null,
    ): self {
        $config = $config ?? ClientConfig::fromEnv($apiKey);
        $requestFactory ??= new RequestFactory();
        $httpClient ??= new Psr18Client(null, [
            'timeout' => $config->getTimeout(),
            'connect_timeout' => $config->getConnectTimeout(),
        ]);

        return new self($config, $httpClient, $requestFactory, $logger);
    }

    /**
     * @internal primarily for testing to avoid real sleeps.
     */
    public function setSleeper(callable $sleeper): void
    {
        $this->sleeper = $sleeper;
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function createGeneration(CreateGenerationRequest $request): CreateGenerationResponse
    {
        $payload = $request->toPayload();
        $body = $this->encodeBody($payload);

        $httpRequest = $this->requestFactory->create(
            'POST',
            $this->buildUri('/generations'),
            $this->config->buildHeaders(),
            $body,
        );

        $response = $this->send($httpRequest, false);
        $data = $this->decodeResponse($response);
        $warnings = $this->extractWarnings($data, 'POST /generations');

        $generationId = $data['generationId'] ?? $data['id'] ?? null;
        if (!is_string($generationId) || $generationId === '') {
            throw new ServerException('generationId missing from response', $response->getStatusCode(), $data, $response);
        }

        return new CreateGenerationResponse($generationId, $warnings);
    }

    public function getGeneration(string $generationId): PendingGeneration|GeneratedGamma
    {
        $httpRequest = $this->requestFactory->create(
            'GET',
            $this->buildUri('/generations/' . $generationId),
            $this->config->buildHeaders(),
        );

        $response = $this->send($httpRequest, true);
        $data = $this->decodeResponse($response);
        $warnings = $this->extractWarnings($data, 'GET /generations');
        $status = (string)($data['status'] ?? '');
        $id = (string)($data['generationId'] ?? $generationId);

        if ($status === 'pending') {
            return new PendingGeneration(
                status: $status,
                generationId: $id,
                estimatedWaitSeconds: isset($data['estimatedWaitSeconds']) ? (int)$data['estimatedWaitSeconds'] : null,
                credits: isset($data['credits']) && is_array($data['credits']) ? $data['credits'] : null,
                warnings: $warnings,
            );
        }

        if ($status === 'completed') {
            $gammaUrl = $this->resolveUrlField($data, ['gammaUrl', 'gamma_url', 'gamma']);
            if ($gammaUrl === null) {
                throw new ServerException('Missing gammaUrl in completed generation payload', $response->getStatusCode(), $data, $response);
            }

            $exportUrls = $this->collectExportUrls($data);

            return new GeneratedGamma(
                status: $status,
                generationId: $id,
                gammaUrl: $gammaUrl,
                pdfUrl: $exportUrls['pdf'] ?? $this->resolveUrlField($data, ['pdfUrl', 'pdf_url']),
                pptxUrl: $exportUrls['pptx'] ?? $this->resolveUrlField($data, ['pptxUrl', 'pptx_url']),
                credits: isset($data['credits']) && is_array($data['credits']) ? $data['credits'] : null,
                exportUrls: $exportUrls,
                warnings: $warnings,
            );
        }

        throw new ServerException(sprintf('Unexpected generation status "%s"', $status), $response->getStatusCode(), $data, $response);
    }

    private function send(RequestInterface $request, bool $allowRetry): ResponseInterface
    {
        return $allowRetry ? $this->sendWithRetry($request) : $this->sendOnce($request);
    }

    private function sendOnce(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new GammaException('HTTP client error: ' . $exception->getMessage(), previous: $exception);
        }

        return $this->handleErrors($response);
    }

    private function sendWithRetry(RequestInterface $request): ResponseInterface
    {
        $attempt = 0;
        $maxAttempts = max(1, $this->config->getRetries() + 1);
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                $response = $this->httpClient->sendRequest($request);
                return $this->handleErrors($response);
            } catch (TooManyRequestsException $exception) {
                $lastException = $exception;
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                $delay = $exception->getRetryAfter();
                if ($delay === null) {
                    $delay = (int)ceil(($this->config->getRetryBackoff())($attempt));
                }

                ($this->sleeper)($delay);
            } catch (ServerException $exception) {
                $lastException = $exception;
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                $this->sleepWithBackoff($attempt);
            } catch (ClientExceptionInterface $exception) {
                $lastException = new GammaException('HTTP client error: ' . $exception->getMessage(), previous: $exception);
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw $lastException;
                }

                $this->sleepWithBackoff($attempt);
            } catch (GammaException $exception) {
                throw $exception;
            }
        }

        assert($lastException instanceof GammaException);

        throw $lastException;
    }

    private function handleErrors(ResponseInterface $response): ResponseInterface
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return $response;
        }

        $data = $this->safeDecode($response);
        $message = $this->resolveErrorMessage($data) ?? 'Gamma API error';

        return match ($status) {
            400 => throw new BadRequestException($message, $status, $data, $response),
            401 => throw new UnauthorizedException($message, $status, $data, $response),
            403 => throw new ForbiddenException($message, $status, $data, $response),
            404 => throw new NotFoundException($message, $status, $data, $response),
            422 => throw new UnprocessableException($message, $status, $data, $response),
            429 => throw new TooManyRequestsException(
                $message,
                $status,
                $this->parseRetryAfter($response),
                $data,
                $response,
            ),
            default => $status >= 500 && $status < 600
                ? throw new ServerException($message, $status, $data, $response)
                : throw new GammaException($message, $status, $data, $response),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveErrorMessage(array $data): ?string
    {
        $candidates = ['message', 'error', 'detail'];
        foreach ($candidates as $candidate) {
            if (isset($data[$candidate]) && is_string($data[$candidate])) {
                return $data[$candidate];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string)$response->getBody();
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new GammaException('Unexpected response payload (not JSON)', $response->getStatusCode(), null, $response);
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeDecode(ResponseInterface $response): array
    {
        try {
            return $this->decodeResponse($response);
        } catch (GammaException) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeBody(array $payload): string
    {
        try {
            return json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new GammaException('Failed to encode request body: ' . $exception->getMessage(), previous: $exception);
        }
    }

    private function buildUri(string $path): string
    {
        $prefix = rtrim($this->config->getBaseUrl(), '/');
        $version = trim($this->config->getApiVersion(), '/');
        $path = '/' . ltrim($path, '/');

        return sprintf('%s/%s%s', $prefix, $version, $path);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, mixed>
     */
    private function extractWarnings(array &$data, string $context): array
    {
        $warnings = [];
        if (!isset($data['warnings']) || !is_array($data['warnings'])) {
            return $warnings;
        }

        $warnings = $data['warnings'];
        foreach ($warnings as $warning) {
            $message = $this->stringifyWarning($warning);
            $this->logger->warning(sprintf('Gamma warning during %s: %s', $context, $message));
        }

        unset($data['warnings']);

        return $warnings;
    }

    private function stringifyWarning(mixed $warning): string
    {
        if (is_string($warning)) {
            return $warning;
        }

        if (is_array($warning)) {
            $parts = [];
            if (isset($warning['code']) && is_string($warning['code'])) {
                $parts[] = $warning['code'];
            }

            if (isset($warning['message']) && is_string($warning['message'])) {
                $parts[] = $warning['message'];
            }

            if ($parts !== []) {
                return implode(': ', $parts);
            }

            $json = json_encode($warning, JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                return $json;
            }
        }

        return 'Unknown warning structure';
    }

    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        if (!$response->hasHeader('Retry-After')) {
            return null;
        }

        $value = $response->getHeaderLine('Retry-After');
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int)$value;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $delta = $timestamp - time();

        return $delta > 0 ? $delta : null;
    }

    private function sleepWithBackoff(int $attempt): void
    {
        $delay = ($this->config->getRetryBackoff())($attempt);
        $delaySeconds = (int)max(0, ceil($delay));
        if ($delaySeconds <= 0) {
            return;
        }

        ($this->sleeper)($delaySeconds);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $candidates
     */
    private function resolveUrlField(array $data, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($data[$candidate]) && is_string($data[$candidate]) && $data[$candidate] !== '') {
                return $data[$candidate];
            }

            if (isset($data['urls']) && is_array($data['urls']) && isset($data['urls'][$candidate]) && is_string($data['urls'][$candidate])) {
                return $data['urls'][$candidate];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function collectExportUrls(array $data): array
    {
        $exportUrls = [];
        if (isset($data['exportUrls']) && is_array($data['exportUrls'])) {
            foreach ($data['exportUrls'] as $key => $value) {
                if (is_string($key) && is_string($value) && $value !== '') {
                    $exportUrls[$key] = $value;
                }
            }
        }

        if (isset($data['urls']) && is_array($data['urls'])) {
            foreach ($data['urls'] as $key => $value) {
                if (is_string($key) && is_string($value) && $value !== '') {
                    $exportUrls[$key] = $value;
                }
            }
        }

        return $exportUrls;
    }
}
