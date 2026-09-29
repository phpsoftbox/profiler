<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Кодирование отчёта профайлера в JSON, которое не падает на данных приложения.
 *
 * В теги попадают произвольные значения (bindings SQL, ответы внешних сервисов), поэтому:
 * - не-UTF-8 байты заменяются на U+FFFD;
 * - `NAN`/`INF` и неподдерживаемые типы (ресурсы) превращаются в `0`/`null` вместо ошибки кодирования.
 */
final class TraceJson
{
    public const int FLAGS = JSON_THROW_ON_ERROR
        | JSON_PARTIAL_OUTPUT_ON_ERROR
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, bool $pretty = false): string
    {
        return json_encode($payload, $pretty ? self::FLAGS | JSON_PRETTY_PRINT : self::FLAGS);
    }
}
