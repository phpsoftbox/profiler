<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Store;

use PhpSoftBox\Profiler\ProfilerStoreInterface;
use PhpSoftBox\Profiler\ProfileTrace;

use function array_reverse;
use function array_slice;

final class InMemoryProfilerStore implements ProfilerStoreInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $traces = [];

    public function save(ProfileTrace $trace): void
    {
        $this->traces[$trace->id()] = $trace->toArray();
    }

    public function find(string $traceId): ?array
    {
        return $this->traces[$traceId] ?? null;
    }

    public function latest(int $limit = 20): array
    {
        return array_slice(array_reverse($this->traces), 0, $limit);
    }
}
