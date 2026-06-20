<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

use Throwable;

use function get_class;
use function memory_get_usage;
use function round;

final class ProfileSpan implements SpanInterface
{
    private ?int $endedAtNs           = null;
    private ?int $endMemory           = null;
    private string $status            = 'ok';
    private ?string $exceptionClass   = null;
    private ?string $exceptionMessage = null;

    /**
     * @param array<string, mixed> $tags
     */
    public function __construct(
        private readonly Profiler $profiler,
        private readonly string $id,
        private readonly ?string $parentId,
        private readonly string $name,
        private readonly ?string $category,
        private array $tags,
        private readonly int $startedAtNs,
        private readonly int $startMemory,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function parentId(): ?string
    {
        return $this->parentId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function addTag(string $name, mixed $value): self
    {
        $this->tags[$name] = $value;

        return $this;
    }

    public function fail(Throwable $exception): self
    {
        $this->status           = 'error';
        $this->exceptionClass   = get_class($exception);
        $this->exceptionMessage = $exception->getMessage();

        return $this;
    }

    public function finish(): void
    {
        $this->profiler->finishSpan($this);
    }

    public function markFinished(int $endedAtNs): void
    {
        if ($this->endedAtNs !== null) {
            return;
        }

        $this->endedAtNs = $endedAtNs;
        $this->endMemory = memory_get_usage(true);
    }

    public function finished(): bool
    {
        return $this->endedAtNs !== null;
    }

    public function durationMs(): ?float
    {
        if ($this->endedAtNs === null) {
            return null;
        }

        return round(($this->endedAtNs - $this->startedAtNs) / 1_000_000, 3);
    }

    public function toArray(): array
    {
        $memoryDelta = null;
        if ($this->endMemory !== null) {
            $memoryDelta = $this->endMemory - $this->startMemory;
        }

        return [
            'id'                => $this->id,
            'parent_id'         => $this->parentId,
            'name'              => $this->name,
            'category'          => $this->category,
            'status'            => $this->status,
            'duration_ms'       => $this->durationMs(),
            'memory_delta'      => $memoryDelta,
            'tags'              => $this->tags,
            'exception_class'   => $this->exceptionClass,
            'exception_message' => $this->exceptionMessage,
        ];
    }
}
