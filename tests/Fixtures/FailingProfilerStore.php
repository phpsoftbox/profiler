<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests\Fixtures;

use PhpSoftBox\Profiler\ProfilerStoreInterface;
use PhpSoftBox\Profiler\ProfileTrace;
use RuntimeException;

/**
 * Хранилище, которое всегда падает при сохранении: имитирует сбой записи трассы.
 */
final class FailingProfilerStore implements ProfilerStoreInterface
{
    public function save(ProfileTrace $trace): void
    {
        throw new RuntimeException('Store is broken.');
    }

    public function find(string $traceId): ?array
    {
        return null;
    }

    public function latest(int $limit = 20): array
    {
        return [];
    }
}
