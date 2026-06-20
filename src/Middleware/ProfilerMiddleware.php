<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Middleware;

use PhpSoftBox\Profiler\ProfilerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final readonly class ProfilerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProfilerInterface $profiler,
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
            $span->addTag('status_code', $response->getStatusCode());
        } catch (Throwable $exception) {
            $span->fail($exception);
            $span->finish();
            $this->profiler->finishTrace();

            throw $exception;
        }

        $span->finish();
        $finishedTrace = $this->profiler->finishTrace() ?? $trace;
        $duration      = $finishedTrace->durationMs() ?? 0.0;

        return $response
            ->withHeader('X-Profile-Id', $finishedTrace->id())
            ->withHeader('Server-Timing', 'app;dur=' . $duration);
    }
}
