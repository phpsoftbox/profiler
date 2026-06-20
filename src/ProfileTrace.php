<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

use DateTimeImmutable;
use DateTimeInterface;

use function array_map;
use function hrtime;
use function memory_get_peak_usage;
use function memory_get_usage;
use function round;

final class ProfileTrace
{
    private readonly DateTimeImmutable $startedAt;
    private readonly int $startedAtNs;
    private readonly int $startMemory;
    private ?int $endedAtNs = null;
    private ?int $endMemory = null;

    /**
     * @var list<ProfileSpan>
     */
    private array $spans = [];

    /**
     * @var list<array{name: string, offset_ms: float, tags: array<string, mixed>}>
     */
    private array $marks = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sections = [];

    /**
     * @param array<string, mixed> $tags
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $type,
        private array $tags = [],
    ) {
        $this->startedAt = new DateTimeImmutable();

        $this->startedAtNs = hrtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function addTag(string $name, mixed $value): self
    {
        $this->tags[$name] = $value;

        return $this;
    }

    public function addSpan(ProfileSpan $span): void
    {
        $this->spans[] = $span;
    }

    /**
     * @param array<string, mixed> $tags
     */
    public function addMark(string $name, array $tags = []): void
    {
        $this->marks[] = [
            'name'      => $name,
            'offset_ms' => round((hrtime(true) - $this->startedAtNs) / 1_000_000, 3),
            'tags'      => $tags,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     */
    public function setSections(array $sections): void
    {
        $this->sections = $sections;
    }

    public function finish(): void
    {
        if ($this->endedAtNs !== null) {
            return;
        }

        $this->endedAtNs = hrtime(true);
        $this->endMemory = memory_get_usage(true);
    }

    public function durationMs(): ?float
    {
        if ($this->endedAtNs === null) {
            return null;
        }

        return round(($this->endedAtNs - $this->startedAtNs) / 1_000_000, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $memoryDelta = null;
        if ($this->endMemory !== null) {
            $memoryDelta = $this->endMemory - $this->startMemory;
        }

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'type'         => $this->type,
            'started_at'   => $this->startedAt->format(DateTimeInterface::ATOM),
            'duration_ms'  => $this->durationMs(),
            'memory_delta' => $memoryDelta,
            'memory_peak'  => memory_get_peak_usage(true),
            'tags'         => $this->tags,
            'spans'        => array_map(static fn (ProfileSpan $span): array => $span->toArray(), $this->spans),
            'marks'        => $this->marks,
            'sections'     => $this->sections,
        ];
    }
}
