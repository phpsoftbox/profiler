<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

use Throwable;

final class NullSpan implements SpanInterface
{
    public function id(): string
    {
        return '';
    }

    public function parentId(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return '';
    }

    public function category(): ?string
    {
        return null;
    }

    public function addTag(string $name, mixed $value): self
    {
        return $this;
    }

    public function fail(Throwable $exception): self
    {
        return $this;
    }

    public function finish(): void
    {
    }

    public function finished(): bool
    {
        return true;
    }

    public function toArray(): array
    {
        return [];
    }
}
