<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Store;

use InvalidArgumentException;
use PhpSoftBox\Profiler\ProfilerStoreInterface;
use PhpSoftBox\Profiler\ProfileTrace;

use function array_key_first;
use function array_reverse;
use function array_slice;
use function array_values;
use function count;

/**
 * Хранит последние трассы в памяти процесса.
 *
 * Число трасс ограничено `$maxTraces`: при переполнении вытесняется самая старая, поэтому долгий воркер не растёт
 * по памяти. `clear()` сбрасывает хранилище целиком (тесты, явный сброс между задачами).
 */
final class InMemoryProfilerStore implements ProfilerStoreInterface
{
    public const int DEFAULT_MAX_TRACES = 100;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $traces = [];

    public function __construct(
        private readonly int $maxTraces = self::DEFAULT_MAX_TRACES,
    ) {
        if ($maxTraces < 1) {
            throw new InvalidArgumentException('Max traces must be at least 1.');
        }
    }

    public function save(ProfileTrace $trace): void
    {
        // Повторно сохранённая трасса становится самой новой.
        unset($this->traces[$trace->id()]);
        $this->traces[$trace->id()] = $trace->toArray();

        while (count($this->traces) > $this->maxTraces) {
            unset($this->traces[array_key_first($this->traces)]);
        }
    }

    public function find(string $traceId): ?array
    {
        return $this->traces[$traceId] ?? null;
    }

    public function latest(int $limit = 20): array
    {
        if ($limit < 1) {
            return [];
        }

        return array_values(array_slice(array_reverse($this->traces), 0, $limit));
    }

    public function clear(): void
    {
        $this->traces = [];
    }
}
