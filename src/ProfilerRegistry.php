<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

final class ProfilerRegistry implements ProfilerRegistryInterface
{
    /**
     * @var array<string, ProfilerCollectorInterface>
     */
    private array $collectors = [];

    public function addCollector(ProfilerCollectorInterface $collector): void
    {
        $this->collectors[$collector->key()] = $collector;
    }

    public function collectors(): array
    {
        return $this->collectors;
    }

    public function collect(ProfileTrace $trace): array
    {
        $sections = [];

        foreach ($this->collectors as $collector) {
            $sections[$collector->key()] = $collector->collect($trace);
        }

        return $sections;
    }

    public function reset(): void
    {
        foreach ($this->collectors as $collector) {
            $collector->reset();
        }
    }
}
