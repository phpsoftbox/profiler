<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests\Store;

use InvalidArgumentException;
use PhpSoftBox\Profiler\ProfileTrace;
use PhpSoftBox\Profiler\Store\FileProfilerStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_diff;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const INF;

#[CoversClass(FileProfilerStore::class)]
#[CoversMethod(FileProfilerStore::class, 'save')]
#[CoversMethod(FileProfilerStore::class, 'find')]
#[CoversMethod(FileProfilerStore::class, 'latest')]
#[CoversMethod(FileProfilerStore::class, '__construct')]
final class FileProfilerStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/psb-profiler-store-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (array_diff(scandir($this->directory) ?: [], ['.', '..']) as $file) {
            unlink($this->directory . '/' . $file);
        }

        rmdir($this->directory);
    }

    /**
     * Проверим, что трасса с не-UTF-8 строкой и INF в тегах сохраняется, а не бросает JsonException.
     *
     * @see FileProfilerStore::save()
     * @see FileProfilerStore::find()
     */
    #[Test]
    public function savesTraceWithInvalidUtf8AndInfinity(): void
    {
        $store = new FileProfilerStore($this->directory);

        $store->save($this->trace('abc', ['binding' => "ok\xc3", 'limit' => INF]));

        $stored = $store->find('abc');

        self::assertIsArray($stored);
        self::assertSame("ok\u{FFFD}", $stored['tags']['binding']);
        self::assertSame(0, $stored['tags']['limit']);
    }

    /**
     * Проверим, что latest() отдаёт трассы от новой к старой с учётом limit.
     *
     * @see FileProfilerStore::latest()
     */
    #[Test]
    public function latestReturnsNewestFirst(): void
    {
        $store = new FileProfilerStore($this->directory);

        foreach (['first', 'second', 'third'] as $id) {
            $store->save($this->trace($id));
        }

        self::assertSame(['third', 'second'], array_column($store->latest(2), 'id'));
    }

    /**
     * Проверим, что сверх maxTraces удаляются самые старые трассы.
     *
     * @see FileProfilerStore::save()
     */
    #[Test]
    public function keepsOnlyMaxTraces(): void
    {
        $store = new FileProfilerStore($this->directory, maxTraces: 2);

        foreach (['first', 'second', 'third'] as $id) {
            $store->save($this->trace($id));
        }

        self::assertCount(2, glob($this->directory . '/*.json') ?: []);
        self::assertNull($store->find('first'));
        self::assertSame(['third', 'second'], array_column($store->latest(), 'id'));
    }

    /**
     * Проверим, что при сохранении удаляются трассы старше maxAgeSeconds.
     *
     * @see FileProfilerStore::save()
     */
    #[Test]
    public function removesTracesOlderThanMaxAge(): void
    {
        mkdir($this->directory, 0775, true);

        // Трасса, сохранённая в начале эпохи, заведомо старше часа.
        file_put_contents($this->directory . '/0000000000000001-old.json', '{"id":"old"}');

        $store = new FileProfilerStore($this->directory, maxAgeSeconds: 3600);

        $store->save($this->trace('fresh'));

        self::assertNull($store->find('old'));
        self::assertSame(['fresh'], array_column($store->latest(), 'id'));
    }

    /**
     * Проверим, что повторное сохранение трассы заменяет файл, а не создаёт второй.
     *
     * @see FileProfilerStore::save()
     */
    #[Test]
    public function resavingTraceReplacesFile(): void
    {
        $store = new FileProfilerStore($this->directory);

        $trace = $this->trace('same');
        $store->save($trace);
        $store->save($trace);

        self::assertCount(1, glob($this->directory . '/*.json') ?: []);
    }

    /**
     * Проверим, что повреждённый файл пропускается в latest() и не роняет отчёт.
     *
     * @see FileProfilerStore::latest()
     * @see FileProfilerStore::find()
     */
    #[Test]
    public function skipsCorruptedFiles(): void
    {
        $store = new FileProfilerStore($this->directory);

        $store->save($this->trace('valid'));

        file_put_contents($this->directory . '/9999999999999999-broken.json', '{"id":');

        self::assertNull($store->find('broken'));
        self::assertSame(['valid'], array_column($store->latest(), 'id'));
    }

    /**
     * Проверим, что id с символами пути или шаблона glob не выходит за каталог хранилища.
     *
     * @see FileProfilerStore::find()
     */
    #[Test]
    public function findRejectsUnsafeTraceId(): void
    {
        $store = new FileProfilerStore($this->directory);

        $store->save($this->trace('valid'));

        self::assertNull($store->find('../valid'));
        self::assertNull($store->find('*'));
    }

    /**
     * Проверим, что нулевой предел числа трасс отклоняется.
     *
     * @see FileProfilerStore::__construct()
     */
    #[Test]
    public function rejectsZeroMaxTraces(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileProfilerStore($this->directory, maxTraces: 0);
    }

    /**
     * @param array<string, mixed> $tags
     */
    private function trace(string $id, array $tags = []): ProfileTrace
    {
        $trace = new ProfileTrace($id, 'test', 'test', $tags);

        $trace->finish();

        return $trace;
    }
}
