<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests\Middleware;

use LogicException;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Profiler\Middleware\ProfilerMiddleware;
use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\Store\FileProfilerStore;
use PhpSoftBox\Profiler\Tests\Fixtures\CallbackHandler;
use PhpSoftBox\Profiler\Tests\Fixtures\FailingProfilerStore;
use PhpSoftBox\Profiler\Tests\Fixtures\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
use RuntimeException;

use function glob;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const NAN;

#[CoversClass(ProfilerMiddleware::class)]
#[CoversMethod(ProfilerMiddleware::class, 'process')]
final class ProfilerMiddlewareTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/psb-profiler-middleware-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * Проверим, что не-UTF-8 строка и NAN в тегах не роняют запрос: трасса сохраняется, ответ получает X-Profile-Id.
     *
     * @see ProfilerMiddleware::process()
     */
    #[Test]
    public function invalidUtf8AndNanTagsDoNotBreakRequest(): void
    {
        $store = new FileProfilerStore($this->directory);

        $profiler = new Profiler(store: $store);

        $middleware = new ProfilerMiddleware($profiler);

        $response = $middleware->process(
            new ServerRequest('GET', 'http://localhost/orders'),
            new CallbackHandler(static function () use ($profiler): ResponseInterface {
                // Bindings SQL с бинарной строкой и вычисление, давшее NAN.
                $profiler->span('db.query', static function (): void {
                }, ['binding' => "\xff\xfe", 'ratio' => NAN]);

                return new Response(200);
            }),
        );

        self::assertSame(200, $response->getStatusCode());

        $traceId = $response->getHeaderLine('X-Profile-Id');

        self::assertNotSame('', $traceId);

        $stored = $store->find($traceId);

        self::assertIsArray($stored);
        self::assertSame("\u{FFFD}\u{FFFD}", $stored['spans'][1]['tags']['binding']);
        self::assertSame(0, $stored['spans'][1]['tags']['ratio']);
    }

    /**
     * Проверим, что сбой хранилища не превращает успешный ответ в ошибку: ответ без X-Profile-Id, сбой в логе.
     *
     * @see ProfilerMiddleware::process()
     */
    #[Test]
    public function storeFailureKeepsSuccessfulResponse(): void
    {
        $logger = new RecordingLogger();

        $middleware = new ProfilerMiddleware(new Profiler(store: new FailingProfilerStore()), $logger);

        $response = $middleware->process(
            new ServerRequest('GET', 'http://localhost/'),
            new CallbackHandler(static fn (): ResponseInterface => new Response(201)),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertFalse($response->hasHeader('X-Profile-Id'));
        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertInstanceOf(RuntimeException::class, $logger->records[0]['context']['exception']);
    }

    /**
     * Проверим, что при сбое хранилища наружу уходит исходное исключение обработчика, а не ошибка профайлера.
     *
     * @see ProfilerMiddleware::process()
     */
    #[Test]
    public function storeFailureDoesNotReplaceHandlerException(): void
    {
        $middleware = new ProfilerMiddleware(new Profiler(store: new FailingProfilerStore()));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Handler failed.');

        $middleware->process(
            new ServerRequest('GET', 'http://localhost/'),
            new CallbackHandler(static function (): ResponseInterface {
                throw new LogicException('Handler failed.');
            }),
        );
    }
}
