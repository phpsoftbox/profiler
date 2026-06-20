<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

interface ProfilerCollectorInterface
{
    public function key(): string;

    /**
     * @return array<string, mixed>
     */
    public function collect(ProfileTrace $trace): array;

    public function reset(): void;
}
