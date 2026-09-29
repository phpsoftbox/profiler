<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler\Tests\Fixtures;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Обработчик запроса, делегирующий в замыкание.
 */
final readonly class CallbackHandler implements RequestHandlerInterface
{
    /**
     * @param Closure(ServerRequestInterface): ResponseInterface $callback
     */
    public function __construct(
        private Closure $callback,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callback)($request);
    }
}
