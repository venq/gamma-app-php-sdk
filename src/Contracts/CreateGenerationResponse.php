<?php

declare(strict_types=1);

namespace Gamma\SDK\Contracts;

final class CreateGenerationResponse
{
    /** @param array<int, mixed> $warnings */
    public function __construct(
        public readonly string $generationId,
        public readonly array $warnings = [],
    ) {
    }
}
