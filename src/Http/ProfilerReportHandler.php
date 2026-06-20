<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Http;

use PhpSoftBox\Profiler\ProfilerInterface;
use PhpSoftBox\Profiler\ProfilerStoreInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class ProfilerReportHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProfilerInterface $profiler,
        private ProfilerStoreInterface $store,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->profiler->enabled()) {
            return $this->json(['error' => 'Profiler is disabled.'], 404);
        }

        $traceId = $request->getAttribute('trace');
        if (is_string($traceId) && $traceId !== '') {
            $trace = $this->store->find($traceId);
            if ($trace === null) {
                return $this->json(['error' => 'Trace not found.'], 404);
            }

            return $this->json(['trace' => $trace]);
        }

        return $this->json([
            'traces' => $this->store->latest(),
        ]);
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle($request);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): ResponseInterface
    {
        $body = $this->streamFactory->createStream(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        return $response->withBody($body);
    }
}
