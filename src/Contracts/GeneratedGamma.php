<?php

declare(strict_types=1);

namespace Gamma\SDK\Contracts;

final class GeneratedGamma
{
    /**
     * @param array<string, mixed>|null $credits
     * @param array<string, string> $exportUrls
     * @param array<int, mixed> $warnings
     */
    public function __construct(
        public readonly string $status,
        public readonly string $generationId,
        public readonly string $gammaUrl,
        public readonly ?string $pdfUrl = null,
        public readonly ?string $pptxUrl = null,
        public readonly ?array $credits = null,
        public readonly array $exportUrls = [],
        public readonly array $warnings = [],
    ) {
    }
}
