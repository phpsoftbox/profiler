<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

interface ProfilerInterface
{
    public function enabled(): bool;

    public function traceId(): ?string;

    /**
     * @param array<string, mixed> $tags
     */
    public function startTrace(string $name, string $type = 'generic', array $tags = []): ProfileTrace;

    public function currentTrace(): ?ProfileTrace;

    public function finishTrace(): ?ProfileTrace;

    /**
     * @param array<string, mixed> $tags
     */
    public function start(string $name, array $tags = [], ?string $category = null): SpanInterface;

    /**
     * @param array<string, mixed> $tags
     */
    public function span(string $name, callable $callback, array $tags = [], ?string $category = null): mixed;

    /**
     * @param array<string, mixed> $tags
     */
    public function mark(string $name, array $tags = []): void;
}
