<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

use Throwable;

interface SpanInterface
{
    public function id(): string;

    public function parentId(): ?string;

    public function name(): string;

    public function category(): ?string;

    public function addTag(string $name, mixed $value): self;

    public function fail(Throwable $exception): self;

    public function finish(): void;

    public function finished(): bool;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
