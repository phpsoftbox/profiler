<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests;

use InvalidArgumentException;
use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\ProfileTrace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileTrace::class)]
#[CoversClass(Profiler::class)]
#[CoversMethod(ProfileTrace::class, 'addMark')]
#[CoversMethod(ProfileTrace::class, 'toArray')]
#[CoversMethod(Profiler::class, 'mark')]
final class ProfileTraceMarkLimitTest extends TestCase
{
    /**
     * Проверим, что после предела mark не сохраняются, а трасса помечается truncated с числом отброшенных.
     *
     * @see Profiler::mark()
     * @see ProfileTrace::addMark()
     * @see ProfileTrace::toArray()
     */
    #[Test]
    public function marksAboveLimitAreDropped(): void
    {
        $profiler = new Profiler(maxMarks: 2);

        $trace = $profiler->startTrace('test.cli', 'cli');

        for ($index = 0; $index < 5; $index++) {
            $profiler->mark('tick');
        }

        $data = $trace->toArray();

        self::assertCount(2, $data['marks']);
        self::assertTrue($data['truncated']);
        self::assertSame(3, $data['dropped_marks']);
        self::assertSame(0, $data['dropped_spans']);
    }

    /**
     * Проверим, что отрицательный предел mark отклоняется.
     *
     * @see ProfileTrace::addMark()
     */
    #[Test]
    public function rejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProfileTrace('id', 'name', 'type', maxMarks: -1);
    }
}
