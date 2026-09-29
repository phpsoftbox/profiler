<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests\Fixtures;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Logger, запоминающий записи для проверок.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public array $records = [];

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
