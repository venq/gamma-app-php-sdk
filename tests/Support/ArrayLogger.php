<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Support;

use InvalidArgumentException;
use Psr\Log\AbstractLogger;
use function sprintf;

final class ArrayLogger extends AbstractLogger
{
    /** @var list<array{level:string,message:string,context:array<string, mixed>}> */
    public array $records = [];

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $this->normaliseToString($level, 'level'),
            'message' => $this->normaliseToString($message, 'message'),
            'context' => $context,
        ];
    }

    private function normaliseToString(mixed $value, string $label): string
    {
        if (is_string($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        throw new InvalidArgumentException(sprintf('Log %s must be string or Stringable.', $label));
    }
}
