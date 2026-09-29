<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests;

use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\Tests\Fixtures\FailingProfilerStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Profiler::class)]
#[CoversMethod(Profiler::class, 'finishTrace')]
final class ProfilerFinishTraceFailureTest extends TestCase
{
    /**
     * Проверим, что сбой хранилища пробрасывается, но трасса уже закрыта и следующий span открывает новую.
     *
     * @see Profiler::finishTrace()
     * @see Profiler::currentTrace()
     */
    #[Test]
    public function storeFailureClosesTrace(): void
    {
        $profiler = new Profiler(store: new FailingProfilerStore());

        $trace = $profiler->startTrace('test.request', 'test');

        try {
            $profiler->finishTrace();
            self::fail('Store failure must be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Store is broken.', $exception->getMessage());
        }

        self::assertNull($profiler->currentTrace());

        $profiler->mark('next');

        self::assertNotSame($trace, $profiler->currentTrace());
    }
}
