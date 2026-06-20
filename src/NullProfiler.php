<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

final class NullProfiler implements ProfilerInterface
{
    private NullSpan $span;

    public function __construct()
    {
        $this->span = new NullSpan();
    }

    public function enabled(): bool
    {
        return false;
    }

    public function traceId(): ?string
    {
        return null;
    }

    public function startTrace(string $name, string $type = 'generic', array $tags = []): ProfileTrace
    {
        return new ProfileTrace('', $name, $type, $tags);
    }

    public function currentTrace(): ?ProfileTrace
    {
        return null;
    }

    public function finishTrace(): ?ProfileTrace
    {
        return null;
    }

    public function start(string $name, array $tags = [], ?string $category = null): SpanInterface
    {
        return $this->span;
    }

    public function span(string $name, callable $callback, array $tags = [], ?string $category = null): mixed
    {
        return $callback();
    }

    public function mark(string $name, array $tags = []): void
    {
    }
}
