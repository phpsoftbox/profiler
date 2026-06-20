<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

interface ProfilerStoreInterface
{
    public function save(ProfileTrace $trace): void;

    public function find(string $traceId): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 20): array;
}
