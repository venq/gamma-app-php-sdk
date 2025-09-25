<?php

declare(strict_types=1);

namespace Gamma\SDK\Config;

use function getenv;
use function max;
use function random_int;
use function rtrim;
use function trim;

final class ClientConfig
{
    public const DEFAULT_BASE_URL = 'https://public-api.gamma.app';
    public const DEFAULT_API_VERSION = 'v0.2';
    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_CONNECT_TIMEOUT = 5.0;
    public const DEFAULT_RETRIES = 2;
    public const DEFAULT_USER_AGENT = 'GammaSDK-PHP/1.0 (+https://github.com/venq/gamma-app-php-sdk)';

    private string $baseUrl;
    private string $apiVersion;
    private ?string $apiKey;
    private float $timeout;
    private float $connectTimeout;
    private int $retries;
    /** @var callable(int):float */
    private $retryBackoff;
    /** @var array<string, string> */
    private array $defaultHeaders;
    private string $userAgent;

    /**
     * @param array<string, string> $defaultHeaders
     * @param callable(int):float|null $retryBackoff
     */
    public function __construct(
        string $baseUrl = self::DEFAULT_BASE_URL,
        string $apiVersion = self::DEFAULT_API_VERSION,
        ?string $apiKey = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        int $retries = self::DEFAULT_RETRIES,
        ?callable $retryBackoff = null,
        array $defaultHeaders = [],
        string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiVersion = trim($apiVersion, '/');
        $this->apiKey = $apiKey !== null ? trim($apiKey) : null;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->retries = max(0, $retries);
        $this->retryBackoff = $retryBackoff ?? self::defaultRetryBackoff();
        $this->defaultHeaders = self::normaliseHeaders($defaultHeaders);
        $this->userAgent = $userAgent;
    }

    public static function fromEnv(?string $apiKey = null): self
    {
        $envKey = getenv('GAMMA_API_KEY');
        $resolvedKey = $apiKey ?? ($envKey !== false ? (string)$envKey : null);

        return new self(apiKey: $resolvedKey);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $timeout = $data['timeout'] ?? self::DEFAULT_TIMEOUT;
        $connectTimeout = $data['connectTimeout'] ?? self::DEFAULT_CONNECT_TIMEOUT;
        $retries = $data['retries'] ?? self::DEFAULT_RETRIES;
        $retryBackoff = $data['retryBackoff'] ?? null;
        $defaultHeaders = $data['defaultHeaders'] ?? [];
        $userAgent = $data['userAgent'] ?? self::DEFAULT_USER_AGENT;

        return new self(
            baseUrl: self::valueAsString($data, 'baseUrl', self::DEFAULT_BASE_URL),
            apiVersion: self::valueAsString($data, 'apiVersion', self::DEFAULT_API_VERSION),
            apiKey: self::valueAsNullableString($data, 'apiKey'),
            timeout: self::valueAsFloat($timeout, self::DEFAULT_TIMEOUT),
            connectTimeout: self::valueAsFloat($connectTimeout, self::DEFAULT_CONNECT_TIMEOUT),
            retries: self::valueAsInt($retries, self::DEFAULT_RETRIES),
            retryBackoff: self::valueAsRetryBackoff($retryBackoff),
            defaultHeaders: self::normaliseHeaders(is_array($defaultHeaders) ? $defaultHeaders : []),
            userAgent: is_string($userAgent) ? $userAgent : self::DEFAULT_USER_AGENT,
        );
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * @return callable(int):float
     */
    public function getRetryBackoff(): callable
    {
        return $this->retryBackoff;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @param array<string, string> $additional
     *
     * @return array<string, string>
     */
    public function buildHeaders(array $additional = []): array
    {
        $headers = $this->defaultHeaders;
        $headers['Accept'] = $headers['Accept'] ?? 'application/json';
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        $headers['User-Agent'] = $headers['User-Agent'] ?? $this->userAgent;

        if ($this->hasApiKey() && $this->apiKey !== null) {
            $headers['X-API-KEY'] = $this->apiKey;
        }

        foreach ($additional as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * @return callable(int):float
     */
    private static function defaultRetryBackoff(): callable
    {
        return static function (int $attempt): float {
            $base = 0.5 * (2 ** max(0, $attempt - 1));
            $jitter = random_int(0, 250) / 1000;

            return $base + $jitter;
        };
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private static function normaliseHeaders(array $headers): array
    {
        $normalised = [];
        foreach ($headers as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $normalised[$key] = $value;
            }
        }

        return $normalised;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function valueAsString(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function valueAsNullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function valueAsFloat(mixed $value, float $default): float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        return $default;
    }

    private static function valueAsInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int)$value;
        }

        return $default;
    }

    /**
     * @param mixed $candidate
     */
    private static function valueAsRetryBackoff(mixed $candidate): ?callable
    {
        if ($candidate === null) {
            return null;
        }

        return is_callable($candidate) ? $candidate : null;
    }
}
