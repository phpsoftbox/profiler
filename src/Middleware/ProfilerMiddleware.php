<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Middleware;

use PhpSoftBox\Profiler\ProfilerInterface;
use PhpSoftBox\Profiler\ProfileTrace;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Открывает трассу `http.request` на время обработки запроса.
 *
 * Профайлер не влияет на результат запроса: сбой сбора или сохранения трассы пишется в лог (если передан logger),
 * ответ отдаётся без `X-Profile-Id`, а исключение обработчика пробрасывается как есть.
 */
final readonly class ProfilerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProfilerInterface $profiler,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->profiler->enabled()) {
            return $handler->handle($request);
        }

        $trace = $this->profiler->startTrace('http.request', 'http', [
            'method' => $request->getMethod(),
            'path'   => $request->getUri()->getPath(),
            'host'   => $request->getUri()->getHost(),
        ]);

        $span = $this->profiler->start('http.handle', category: 'http');

        try {
            $response = $handler->handle($request);
        } catch (Throwable $exception) {
            $span->fail($exception);
            $span->finish();
            $this->finishTrace();

            throw $exception;
        }

        $span->addTag('status_code', $response->getStatusCode());
        $span->finish();

        $finished = $this->finishTrace();
        if ($finished === false) {
            return $response;
        }

        $finished ??= $trace;

        return $response
            ->withHeader('X-Profile-Id', $finished->id())
            ->withHeader('Server-Timing', 'app;dur=' . ($finished->durationMs() ?? 0.0));
    }

    /**
     * @return ProfileTrace|false|null `false` — трасса не собрана или не сохранена
     */
    private function finishTrace(): ProfileTrace|false|null
    {
        try {
            return $this->profiler->finishTrace();
        } catch (Throwable $exception) {
            $this->logger?->warning('Profiler trace was not saved: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
