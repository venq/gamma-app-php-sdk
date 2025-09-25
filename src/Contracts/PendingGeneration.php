<?php

declare(strict_types=1);

namespace Gamma\SDK\Contracts;

final class PendingGeneration
{
    /**
     * @param array<string, mixed>|null $credits
     * @param array<int, mixed> $warnings
     */
    public function __construct(
        public readonly string $status,
        public readonly string $generationId,
        public readonly ?int $estimatedWaitSeconds = null,
        public readonly ?array $credits = null,
        public readonly array $warnings = [],
    ) {
    }
}
