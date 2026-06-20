<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests;

use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\ProfilerCollectorInterface;
use PhpSoftBox\Profiler\ProfilerRegistry;
use PhpSoftBox\Profiler\ProfileTrace;
use PhpSoftBox\Profiler\Store\InMemoryProfilerStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Profiler::class)]
final class ProfilerTest extends TestCase
{
    /**
     * Проверяет вложенные spans и сохранение trace в store.
     */
    public function testCollectsNestedSpans(): void
    {
        $store = new InMemoryProfilerStore();

        $profiler = new Profiler(store: $store);

        $trace = $profiler->startTrace('test.request', 'test');
        $profiler->span('outer', function () use ($profiler): void {
            $profiler->span('inner', static function (): void {
            });
        });
        $finished = $profiler->finishTrace();

        $this->assertSame($trace, $finished);

        $stored = $store->find($trace->id());

        $this->assertIsArray($stored);
        $this->assertCount(2, $stored['spans']);
        $this->assertSame('outer', $stored['spans'][0]['name']);
        $this->assertSame('inner', $stored['spans'][1]['name']);
        $this->assertSame($stored['spans'][0]['id'], $stored['spans'][1]['parent_id']);
    }

    /**
     * Проверяет, что exception помечает span как failed и пробрасывается дальше.
     */
    public function testMarksSpanAsFailedOnException(): void
    {
        $store = new InMemoryProfilerStore();

        $profiler = new Profiler(store: $store);

        $trace = $profiler->startTrace('test.request', 'test');

        try {
            $profiler->span('broken', static function (): void {
                throw new RuntimeException('Broken');
            });
        } catch (RuntimeException) {
        }

        $profiler->finishTrace();
        $stored = $store->find($trace->id());

        $this->assertSame('error', $stored['spans'][0]['status']);
        $this->assertSame(RuntimeException::class, $stored['spans'][0]['exception_class']);
    }

    /**
     * Проверяет, что collectors добавляют отдельные sections в итоговый report.
     */
    public function testCollectsRegisteredSections(): void
    {
        $registry = new ProfilerRegistry();

        $registry->addCollector(new class () implements ProfilerCollectorInterface {
            public function key(): string
            {
                return 'custom';
            }

            public function collect(ProfileTrace $trace): array
            {
                return ['trace_id' => $trace->id()];
            }

            public function reset(): void
            {
            }
        });

        $store = new InMemoryProfilerStore();

        $profiler = new Profiler(store: $store, registry: $registry);

        $trace = $profiler->startTrace('test.request', 'test');
        $profiler->finishTrace();

        $stored = $store->find($trace->id());

        $this->assertArrayHasKey('custom', $stored['sections']);
        $this->assertSame($trace->id(), $stored['sections']['custom']['trace_id']);
    }
}
