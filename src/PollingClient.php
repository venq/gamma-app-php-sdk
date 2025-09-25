<?php

declare(strict_types=1);

namespace Gamma\SDK;

use Gamma\SDK\Contracts\GeneratedGamma;
use Gamma\SDK\Contracts\PendingGeneration;
use Gamma\SDK\Exceptions\GammaException;
use Gamma\SDK\Exceptions\ServerException;
use Gamma\SDK\Exceptions\TooManyRequestsException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function ceil;
use function max;
use function microtime;
use function min;
use function sleep;
use function sprintf;

final class PollingClient
{
    private LoggerInterface $logger;
    /** @var callable(int):void */
    private $sleeper;

    public function __construct(
        private readonly GammaClient $client,
        ?LoggerInterface $logger = null,
        ?callable $sleeper = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            if ($seconds > 0) {
                sleep($seconds);
            }
        };
    }

    public function waitUntilCompleted(string $generationId, int $intervalSeconds = 5, int $timeoutSeconds = 300): GeneratedGamma
    {
        if ($intervalSeconds <= 0) {
            throw new InvalidArgumentException('Interval seconds must be positive.');
        }

        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('Timeout seconds must be positive.');
        }

        $startedAt = microtime(true);

        while (true) {
            $elapsed = microtime(true) - $startedAt;
            if ($elapsed >= $timeoutSeconds) {
                throw new GammaException(sprintf('Polling timed out after %d seconds for generation %s', $timeoutSeconds, $generationId));
            }

            try {
                $result = $this->client->getGeneration($generationId);
            } catch (TooManyRequestsException $exception) {
                $retryAfter = $exception->getRetryAfter() ?? $intervalSeconds;
                $this->logger->warning('Gamma API throttled polling request; retrying after {seconds}s', ['seconds' => $retryAfter]);
                ($this->sleeper)(max(1, (int)ceil($retryAfter)));
                continue;
            } catch (ServerException $exception) {
                $this->logger->warning('Gamma API server error while polling: {message}', ['message' => $exception->getMessage()]);
                ($this->sleeper)(max(1, $intervalSeconds));
                continue;
            }


            if ($result instanceof GeneratedGamma) {
                return $result;
            }

            if (!$result instanceof PendingGeneration) {
                throw new GammaException('Unexpected response during polling.');
            }

            $wait = $result->estimatedWaitSeconds ?? $intervalSeconds;
            $remaining = $timeoutSeconds - $elapsed;
            $sleepFor = (int)max(1, min($wait, (int)ceil($remaining)));
            ($this->sleeper)($sleepFor);
        }
    }
}

