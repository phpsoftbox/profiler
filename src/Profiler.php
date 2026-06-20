<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

use Throwable;

use function array_pop;
use function array_values;
use function bin2hex;
use function count;
use function hrtime;
use function memory_get_usage;
use function random_bytes;

final class Profiler implements ProfilerInterface
{
    private ?ProfileTrace $trace = null;

    /**
     * @var list<ProfileSpan>
     */
    private array $spanStack = [];

    public function __construct(
        private readonly bool $enabled = true,
        private readonly ?ProfilerStoreInterface $store = null,
        private readonly ?ProfilerRegistryInterface $registry = null,
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function traceId(): ?string
    {
        return $this->trace?->id();
    }

    public function startTrace(string $name, string $type = 'generic', array $tags = []): ProfileTrace
    {
        if (!$this->enabled) {
            return new ProfileTrace('', $name, $type, $tags);
        }

        $this->registry?->reset();
        $this->spanStack = [];
        $this->trace     = new ProfileTrace($this->newId(), $name, $type, $tags);

        return $this->trace;
    }

    public function currentTrace(): ?ProfileTrace
    {
        return $this->trace;
    }

    public function finishTrace(): ?ProfileTrace
    {
        if (!$this->enabled || $this->trace === null) {
            return null;
        }

        while ($this->spanStack !== []) {
            $span = array_pop($this->spanStack);
            $span->markFinished(hrtime(true));
        }

        $trace = $this->trace;
        $trace->finish();
        $trace->setSections($this->registry?->collect($trace) ?? []);

        $this->store?->save($trace);
        $this->trace = null;

        return $trace;
    }

    public function start(string $name, array $tags = [], ?string $category = null): SpanInterface
    {
        if (!$this->enabled) {
            return new NullSpan();
        }

        $trace  = $this->trace ?? $this->startTrace('application.lifecycle');
        $parent = $this->spanStack[count($this->spanStack) - 1] ?? null;

        $span = new ProfileSpan(
            profiler: $this,
            id: $this->newId(),
            parentId: $parent?->id(),
            name: $name,
            category: $category,
            tags: $tags,
            startedAtNs: hrtime(true),
            startMemory: memory_get_usage(true),
        );

        $trace->addSpan($span);
        $this->spanStack[] = $span;

        return $span;
    }

    public function span(string $name, callable $callback, array $tags = [], ?string $category = null): mixed
    {
        $span = $this->start($name, $tags, $category);

        try {
            return $callback();
        } catch (Throwable $exception) {
            $span->fail($exception);

            throw $exception;
        } finally {
            $span->finish();
        }
    }

    public function mark(string $name, array $tags = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $trace = $this->trace ?? $this->startTrace('application.lifecycle');
        $trace->addMark($name, $tags);
    }

    public function finishSpan(ProfileSpan $span): void
    {
        if ($span->finished()) {
            return;
        }

        $span->markFinished(hrtime(true));
        $this->removeSpanFromStack($span);
    }

    private function removeSpanFromStack(ProfileSpan $span): void
    {
        $last = $this->spanStack[count($this->spanStack) - 1] ?? null;
        if ($last === $span) {
            array_pop($this->spanStack);

            return;
        }

        foreach ($this->spanStack as $index => $activeSpan) {
            if ($activeSpan === $span) {
                unset($this->spanStack[$index]);
                $this->spanStack = array_values($this->spanStack);

                return;
            }
        }
    }

    private function newId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
