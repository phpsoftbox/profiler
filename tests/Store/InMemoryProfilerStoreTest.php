<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests\Store;

use InvalidArgumentException;
use PhpSoftBox\Profiler\ProfileTrace;
use PhpSoftBox\Profiler\Store\InMemoryProfilerStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_is_list;

#[CoversClass(InMemoryProfilerStore::class)]
#[CoversMethod(InMemoryProfilerStore::class, '__construct')]
#[CoversMethod(InMemoryProfilerStore::class, 'save')]
#[CoversMethod(InMemoryProfilerStore::class, 'latest')]
#[CoversMethod(InMemoryProfilerStore::class, 'clear')]
final class InMemoryProfilerStoreTest extends TestCase
{
    /**
     * Проверим, что сверх maxTraces вытесняется самая старая трасса — память воркера не растёт.
     *
     * @see InMemoryProfilerStore::save()
     * @see InMemoryProfilerStore::find()
     */
    #[Test]
    public function evictsOldestAboveLimit(): void
    {
        $store = new InMemoryProfilerStore(maxTraces: 2);

        foreach (['first', 'second', 'third'] as $id) {
            $store->save(new ProfileTrace($id, 'test', 'test'));
        }

        self::assertNull($store->find('first'));
        self::assertSame(['third', 'second'], array_column($store->latest(), 'id'));
    }

    /**
     * Проверим, что повторно сохранённая трасса становится самой новой и не вытесняется первой.
     *
     * @see InMemoryProfilerStore::save()
     */
    #[Test]
    public function resavedTraceBecomesNewest(): void
    {
        $store = new InMemoryProfilerStore(maxTraces: 2);

        $first = new ProfileTrace('first', 'test', 'test');

        $store->save($first);
        $store->save(new ProfileTrace('second', 'test', 'test'));
        $store->save($first);
        $store->save(new ProfileTrace('third', 'test', 'test'));

        self::assertSame(['third', 'first'], array_column($store->latest(), 'id'));
    }

    /**
     * Проверим, что latest() отдаёт список с limit, а не ассоциативный массив.
     *
     * @see InMemoryProfilerStore::latest()
     */
    #[Test]
    public function latestReturnsListWithLimit(): void
    {
        $store = new InMemoryProfilerStore();

        foreach (['first', 'second', 'third'] as $id) {
            $store->save(new ProfileTrace($id, 'test', 'test'));
        }

        $latest = $store->latest(2);

        self::assertTrue(array_is_list($latest));
        self::assertSame(['third', 'second'], array_column($latest, 'id'));
    }

    /**
     * Проверим, что clear() удаляет все трассы.
     *
     * @see InMemoryProfilerStore::clear()
     */
    #[Test]
    public function clearRemovesAllTraces(): void
    {
        $store = new InMemoryProfilerStore();

        $store->save(new ProfileTrace('first', 'test', 'test'));

        $store->clear();

        self::assertNull($store->find('first'));
        self::assertSame([], $store->latest());
    }

    /**
     * Проверим, что нулевой предел числа трасс отклоняется.
     *
     * @see InMemoryProfilerStore::__construct()
     */
    #[Test]
    public function rejectsZeroMaxTraces(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InMemoryProfilerStore(maxTraces: 0);
    }
}
