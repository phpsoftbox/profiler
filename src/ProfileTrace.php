<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

use function array_map;
use function count;
use function hrtime;
use function memory_get_peak_usage;
use function memory_get_usage;
use function round;

final class ProfileTrace
{
    /**
     * Предел числа span по умолчанию: защищает долгий процесс с незакрытой трассой от роста памяти.
     */
    public const int DEFAULT_MAX_SPANS = 5000;

    /**
     * Предел числа mark по умолчанию: та же защита, что и для span.
     */
    public const int DEFAULT_MAX_MARKS = 5000;

    private readonly DateTimeImmutable $startedAt;
    private readonly int $startedAtNs;
    private readonly int $startMemory;
    private ?int $endedAtNs = null;
    private ?int $endMemory = null;

    /**
     * @var list<ProfileSpan>
     */
    private array $spans = [];

    private int $droppedSpans = 0;

    /**
     * @var list<array{name: string, offset_ms: float, tags: array<string, mixed>}>
     */
    private array $marks = [];

    private int $droppedMarks = 0;

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
        private readonly int $maxSpans = self::DEFAULT_MAX_SPANS,
        private readonly int $maxMarks = self::DEFAULT_MAX_MARKS,
    ) {
        if ($maxSpans < 0) {
            throw new InvalidArgumentException('Max spans must not be negative.');
        }

        if ($maxMarks < 0) {
            throw new InvalidArgumentException('Max marks must not be negative.');
        }

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
        // После предела считаем только отброшенные span: трасса остаётся полезной, а память не растёт.
        if (count($this->spans) >= $this->maxSpans) {
            $this->droppedSpans++;

            return;
        }

        $this->spans[] = $span;
    }

    /**
     * Были ли отброшены span или mark из-за предела.
     */
    public function truncated(): bool
    {
        return $this->droppedSpans > 0 || $this->droppedMarks > 0;
    }

    public function droppedSpans(): int
    {
        return $this->droppedSpans;
    }

    public function droppedMarks(): int
    {
        return $this->droppedMarks;
    }

    /**
     * @param array<string, mixed> $tags
     */
    public function addMark(string $name, array $tags = []): void
    {
        if (count($this->marks) >= $this->maxMarks) {
            $this->droppedMarks++;

            return;
        }

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
            'id'            => $this->id,
            'name'          => $this->name,
            'type'          => $this->type,
            'started_at'    => $this->startedAt->format(DateTimeInterface::ATOM),
            'duration_ms'   => $this->durationMs(),
            'memory_delta'  => $memoryDelta,
            'memory_peak'   => memory_get_peak_usage(true),
            'tags'          => $this->tags,
            'spans'         => array_map(static fn (ProfileSpan $span): array => $span->toArray(), $this->spans),
            'truncated'     => $this->truncated(),
            'dropped_spans' => $this->droppedSpans,
            'marks'         => $this->marks,
            'dropped_marks' => $this->droppedMarks,
            'sections'      => $this->sections,
        ];
    }
}
