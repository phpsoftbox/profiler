<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

interface ProfilerRegistryInterface
{
    public function addCollector(ProfilerCollectorInterface $collector): void;

    /**
     * @return array<string, ProfilerCollectorInterface>
     */
    public function collectors(): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function collect(ProfileTrace $trace): array;

    public function reset(): void;
}
