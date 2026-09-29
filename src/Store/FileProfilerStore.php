<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Store;

use InvalidArgumentException;
use JsonException;
use PhpSoftBox\Profiler\ProfilerStoreInterface;
use PhpSoftBox\Profiler\ProfileTrace;
use PhpSoftBox\Profiler\TraceJson;
use RuntimeException;

use function array_reverse;
use function array_slice;
use function basename;
use function count;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function json_decode;
use function microtime;
use function mkdir;
use function preg_match;
use function rename;
use function rtrim;
use function sprintf;
use function strstr;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Хранит каждую трассу отдельным JSON-файлом `<время сохранения в мкс>-<id трассы>.json`.
 *
 * Время в начале имени упорядочивает файлы: `latest()` берёт последние по имени без чтения mtime каждого файла,
 * а retention по возрасту не обращается к файловой системе ради дат. Каталог ограничен: после каждого сохранения
 * удаляются трассы старше `$maxAgeSeconds` и самые старые сверх `$maxTraces`.
 */
final readonly class FileProfilerStore implements ProfilerStoreInterface
{
    public const int DEFAULT_MAX_TRACES = 500;

    public const int DEFAULT_MAX_AGE_SECONDS = 86400;

    private const string TRACE_ID_PATTERN = '/^[A-Za-z0-9_]+$/';

    private string $directory;

    /**
     * @param int $maxTraces сколько последних трасс хранить, не меньше 1
     * @param int|null $maxAgeSeconds сколько секунд хранить трассу; `null` — без ограничения по возрасту
     */
    public function __construct(
        string $directory,
        private int $maxTraces = self::DEFAULT_MAX_TRACES,
        private ?int $maxAgeSeconds = self::DEFAULT_MAX_AGE_SECONDS,
    ) {
        if ($maxTraces < 1) {
            throw new InvalidArgumentException('Max traces must be at least 1.');
        }

        if ($maxAgeSeconds !== null && $maxAgeSeconds < 1) {
            throw new InvalidArgumentException('Max age must be at least 1 second or null.');
        }

        $this->directory = rtrim($directory, '/');
    }

    public function save(ProfileTrace $trace): void
    {
        $traceId = $trace->id();
        if (preg_match(self::TRACE_ID_PATTERN, $traceId) !== 1) {
            throw new InvalidArgumentException('Invalid trace id for file store: "' . $traceId . '".');
        }

        $this->ensureDirectory();

        // Повторное сохранение той же трассы заменяет прежний файл, а не добавляет второй.
        foreach ($this->filesOf($traceId) as $file) {
            $this->remove($file);
        }

        $name = sprintf('%016d', $this->nowMicroseconds()) . '-' . $traceId . '.json';
        $path = $this->directory . '/' . $name;
        $temp = $this->directory . '/.' . $name . '.tmp';

        // Запись через временный файл: читатель не увидит наполовину записанный JSON.
        if (file_put_contents($temp, TraceJson::encode($trace->toArray(), true)) === false || !rename($temp, $path)) {
            $this->remove($temp);

            throw new RuntimeException('Unable to write profiler trace: ' . $path);
        }

        $this->prune();
    }

    public function find(string $traceId): ?array
    {
        if (preg_match(self::TRACE_ID_PATTERN, $traceId) !== 1) {
            return null;
        }

        $files = $this->filesOf($traceId);
        if ($files === []) {
            return null;
        }

        return $this->read($files[count($files) - 1]);
    }

    public function latest(int $limit = 20): array
    {
        if ($limit < 1) {
            return [];
        }

        $traces = [];
        foreach (array_reverse(array_slice($this->files(), -$limit)) as $file) {
            $trace = $this->read($file);
            if ($trace !== null) {
                $traces[] = $trace;
            }
        }

        return $traces;
    }

    /**
     * Файлы трасс от старых к новым: glob сортирует по имени, а имя начинается со времени сохранения.
     *
     * @return list<string>
     */
    private function files(): array
    {
        return glob($this->directory . '/*.json') ?: [];
    }

    /**
     * @return list<string>
     */
    private function filesOf(string $traceId): array
    {
        return glob($this->directory . '/*-' . $traceId . '.json') ?: [];
    }

    private function prune(): void
    {
        $files = $this->files();

        if ($this->maxAgeSeconds !== null) {
            $threshold = $this->nowMicroseconds() - $this->maxAgeSeconds * 1_000_000;
            $fresh     = [];

            foreach ($files as $file) {
                if ($this->savedAtMicroseconds($file) < $threshold) {
                    $this->remove($file);

                    continue;
                }

                $fresh[] = $file;
            }

            $files = $fresh;
        }

        foreach (array_slice($files, 0, count($files) - $this->maxTraces) as $file) {
            $this->remove($file);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $file): ?array
    {
        $payload = @file_get_contents($file);
        if ($payload === false) {
            // Файл мог удалить retention параллельного запроса.
            return null;
        }

        try {
            $trace = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($trace) ? $trace : null;
    }

    private function savedAtMicroseconds(string $file): int
    {
        $prefix = strstr(basename($file), '-', true);

        return $prefix === false ? 0 : (int) $prefix;
    }

    private function nowMicroseconds(): int
    {
        return (int) (microtime(true) * 1_000_000);
    }

    private function remove(string $file): void
    {
        // Параллельный запрос мог удалить файл раньше — это не ошибка.
        @unlink($file);
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create profiler directory: ' . $this->directory);
        }
    }
}
