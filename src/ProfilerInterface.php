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

    /**
     * Закрывает текущую трассу, собирает sections и сохраняет её в хранилище.
     *
     * Трасса закрывается до сбора и сохранения: если collector или хранилище бросили исключение, оно пробрасывается,
     * но следующий span начнёт новую трассу.
     */
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
