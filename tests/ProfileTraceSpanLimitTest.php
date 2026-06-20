<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests;

use InvalidArgumentException;
use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\ProfileTrace;
use PhpSoftBox\Profiler\Store\InMemoryProfilerStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileTrace::class)]
#[CoversClass(Profiler::class)]
#[CoversMethod(ProfileTrace::class, 'addSpan')]
#[CoversMethod(ProfileTrace::class, 'toArray')]
#[CoversMethod(Profiler::class, 'startTrace')]
final class ProfileTraceSpanLimitTest extends TestCase
{
    /**
     * Проверим, что после предела span не сохраняются, а трасса помечается truncated с числом отброшенных.
     *
     * @see ProfileTrace::addSpan()
     * @see ProfileTrace::toArray()
     */
    #[Test]
    public function spansAboveLimitAreDropped(): void
    {
        $profiler = new Profiler(maxSpans: 3);

        $trace = $profiler->startTrace('test.cli', 'cli');

        for ($index = 0; $index < 5; $index++) {
            $profiler->span('db.query', static function (): void {
            });
        }

        $data = $trace->toArray();

        self::assertCount(3, $data['spans']);
        self::assertTrue($data['truncated']);
        self::assertSame(2, $data['dropped_spans']);
    }

    /**
     * Проверим, что трасса в пределах лимита не помечается truncated.
     *
     * @see ProfileTrace::toArray()
     */
    #[Test]
    public function traceWithinLimitIsNotTruncated(): void
    {
        $profiler = new Profiler();

        $trace = $profiler->startTrace('test.request', 'http');

        $profiler->span('router.match', static function (): void {
        });

        $data = $trace->toArray();

        self::assertCount(1, $data['spans']);
        self::assertFalse($data['truncated']);
        self::assertSame(0, $data['dropped_spans']);
    }

    /**
     * Проверим, что неявная трасса application.lifecycle тоже ограничена: это случай долгой dev-команды.
     *
     * @see Profiler::startTrace()
     */
    #[Test]
    public function implicitLifecycleTraceIsLimited(): void
    {
        $store = new InMemoryProfilerStore();

        $profiler = new Profiler(store: $store, maxSpans: 2);

        // Без startTrace() первый span сам открывает трассу application.lifecycle.
        for ($index = 0; $index < 4; $index++) {
            $profiler->span('db.query', static function (): void {
            });
        }

        $trace = $profiler->finishTrace();

        self::assertNotNull($trace);
        self::assertSame('application.lifecycle', $trace->name());
        self::assertSame(2, $trace->droppedSpans());
    }

    /**
     * Проверим, что отрицательный предел отклоняется.
     *
     * @see ProfileTrace::addSpan()
     */
    #[Test]
    public function rejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProfileTrace('id', 'name', 'type', maxSpans: -1);
    }
}
