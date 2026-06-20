<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Store;

use PhpSoftBox\Profiler\ProfilerStoreInterface;
use PhpSoftBox\Profiler\ProfileTrace;
use RuntimeException;

use function array_slice;
use function basename;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function rtrim;
use function usort;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class FileProfilerStore implements ProfilerStoreInterface
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function save(ProfileTrace $trace): void
    {
        $this->ensureDirectory();

        file_put_contents(
            $this->path($trace->id()),
            json_encode($trace->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public function find(string $traceId): ?array
    {
        $path = $this->path(basename($traceId));
        if (!is_file($path)) {
            return null;
        }

        $payload = file_get_contents($path);
        if ($payload === false) {
            return null;
        }

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    public function latest(int $limit = 20): array
    {
        $files = glob(rtrim($this->directory, '/') . '/*.json') ?: [];
        usort($files, static function (string $left, string $right): int {
            return (filemtime($right) ?: 0) <=> (filemtime($left) ?: 0);
        });

        $traces = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $payload = file_get_contents($file);
            if ($payload === false) {
                continue;
            }

            $traces[] = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        return $traces;
    }

    private function path(string $traceId): string
    {
        return rtrim($this->directory, '/') . '/' . $traceId . '.json';
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
