# Profiler

`phpsoftbox/profiler` — легкое ядро профилирования lifecycle приложения.

Компонент не знает внутренности `Database`, `ORM`, `Router`, `Container` и
других пакетов. Он предоставляет общий runtime API, registry collectors и
хранилища trace. Каждый компонент реализует свою интеграцию самостоятельно.

## Базовое использование

```php
use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\ProfilerRegistry;
use PhpSoftBox\Profiler\Store\FileProfilerStore;

$registry = new ProfilerRegistry();
$profiler = new Profiler(
    enabled: true,
    store: new FileProfilerStore(__DIR__ . '/local/profiler'),
    registry: $registry,
);

$profiler->startTrace('http.request', 'http');

$profiler->span('shipment.sort', function () {
    // измеряемый участок
}, tags: ['shipment_id' => 123]);

$trace = $profiler->finishTrace();
```

## Component extensions

Компоненты регистрируют collectors через `ProfilerExtensionInterface`.

```php
$registry = new ProfilerRegistry();

foreach ($extensions as $extension) {
    $extension->register($registry);
}
```

Пример ожидаемых extensions:

- `PhpSoftBox\Database\Profiler\DatabaseProfilerExtension`;
- `PhpSoftBox\Orm\Profiler\OrmProfilerExtension`;
- `PhpSoftBox\Container\Profiler\ContainerProfilerExtension`;
- `PhpSoftBox\Router\Profiler\RouterProfilerExtension`;
- `PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerExtension`;
- `PhpSoftBox\Inertia\Profiler\InertiaProfilerExtension`;
- `PhpSoftBox\Cache\Profiler\CacheProfilerExtension`;
- `PhpSoftBox\Resource\Profiler\ResourceProfilerExtension`.

## Память в долгих процессах

Collectors и trace живут в памяти процесса, поэтому в воркерах очереди, планировщике и долгих CLI-командах
действуют три правила.

**Collectors пишут только внутри активной трассы.** Место записи проверяет профайлер:

```php
if ($this->profiler?->enabled() !== true || $this->profiler->currentTrace() === null) {
    return;
}

$this->profilerCollector?->recordQuery(/* ... */);
```

Collector очищается только в `startTrace()`, а при выключенном профайлере `startTrace()` его не вызывает.
Без проверки запись вне трассы никто не прочитает и не очистит — массив растёт весь процесс. Так устроены
встроенные интеграции Database, MultiTenant и Router; `ContainerProfilerCollector` агрегирует по id сервиса,
его размер ограничен числом сервисов.

**Списки collectors ограничены.** `DatabaseProfilerCollector`, `MultiTenantProfilerCollector` и
`RouterProfilerCollector` хранят не больше 5 000 записей (параметр конструктора `maxItems`). После предела
растут только счётчики, а в `collect()` появляются `truncated: true` и `dropped` — число отброшенных записей.

**Число span в trace ограничено.** `ProfileTrace` хранит не больше 5 000 span (`Profiler::__construct(maxSpans: ...)`).
После предела span не сохраняются, в `toArray()` — `truncated: true` и `dropped_spans`. Это важно при
включённом профайлере: `start()` без активной трассы сам открывает `application.lifecycle`, и в долгой
dev-команде эту трассу никто не закрывает.

## HTTP middleware

```php
use PhpSoftBox\Profiler\Middleware\ProfilerMiddleware;

$app->add(ProfilerMiddleware::class);
```

Middleware создает root trace `http.request`, добавляет `X-Profile-Id` и
`Server-Timing`.

## JSON API

Для dev-панели можно подключить `ProfilerReportHandler`:

```php
use PhpSoftBox\Profiler\Http\ProfilerReportHandler;

$routes->get('/__profiler/api/traces', ProfilerReportHandler::class);
$routes->get('/__profiler/api/traces/{trace}', ProfilerReportHandler::class);
```

Handler возвращает `404`, если профайлер выключен.

## React debug panel

Backend должен отдать в Inertia shared props:

```php
'profiler' => [
    'enabled'  => $profiler->enabled(),
    'trace_id' => $profiler->traceId(),
    'endpoint' => '/__profiler',
],
```

На frontend:

```tsx
import { DebugProvider, ProfilerDebugPanel } from '@phpsoftbox/profiler-js';

<DebugProvider profiler={page.props.profiler}>
    <App />
    <ProfilerDebugPanel />
</DebugProvider>
```

`@phpsoftbox/profiler-js` читает report по `trace_id`, показывает timeline и
компонентные sections: `database`, `orm`, `container`, `router`, `multi_tenant`, `inertia`.
