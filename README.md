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

Встроенные extensions пакетов фреймворка:

- `PhpSoftBox\Container\Profiler\ContainerProfilerExtension` — section `container`;
- `PhpSoftBox\Database\Profiler\DatabaseProfilerExtension` — section `database`;
- `PhpSoftBox\Router\Profiler\RouterProfilerExtension` — section `router`;
- `PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerExtension` — section `multi_tenant`.

Другие пакеты (ORM, Inertia, Cache, Resource) своих extensions не имеют; их участки можно измерять через
`span()`/`mark()` или собственный collector.

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

**Число span и mark в trace ограничено.** `ProfileTrace` хранит не больше 5 000 span и 5 000 mark
(`Profiler::__construct(maxSpans: ..., maxMarks: ...)`). После предела они не сохраняются, в `toArray()` —
`truncated: true`, `dropped_spans` и `dropped_marks`. Это важно при включённом профайлере: `start()` и `mark()`
без активной трассы сами открывают `application.lifecycle`, и в долгой dev-команде эту трассу никто не закрывает.

## Хранилища

Оба хранилища ограничены по размеру.

**`InMemoryProfilerStore(maxTraces: 100)`** — последние трассы в памяти процесса. При переполнении вытесняется
самая старая, поэтому воркер не растёт по памяти. `clear()` очищает хранилище целиком. Пакет не зависит от
`phpsoftbox/container`, поэтому `ResetInterface` не реализует; сбрасывать хранилище после каждой задачи обычно
не нужно — тогда панель не увидит предыдущие запросы. Если сброс всё же нужен, подключите `clear` в карту hooks
`ServicesResetter` (`InMemoryProfilerStore::class => 'clear'`).

**`FileProfilerStore($directory, maxTraces: 500, maxAgeSeconds: 86400)`** — трасса на файл
`<время сохранения в мкс>-<id>.json`. После каждого сохранения удаляются трассы старше `maxAgeSeconds`
(`null` — без ограничения по возрасту) и самые старые сверх `maxTraces`. Время в имени упорядочивает файлы:
`latest()` берёт последние по имени без `filemtime()` каждого файла. Файл пишется через временный и `rename()`,
повреждённые файлы `find()`/`latest()` пропускают. Id трассы — только `[A-Za-z0-9_]`.

## Сбои сериализации и сохранения

В теги попадают данные приложения (bindings SQL, ответы API), поэтому отчёт кодируется через `TraceJson`:
не-UTF-8 байты заменяются на `U+FFFD`, `NAN`/`INF` и ресурсы — на `0`/`null` (`JSON_INVALID_UTF8_SUBSTITUTE`,
`JSON_PARTIAL_OUTPUT_ON_ERROR`). Так кодируют `FileProfilerStore` и `ProfilerReportHandler`.

`Profiler::finishTrace()` закрывает трассу до сбора sections и сохранения: если collector или хранилище бросили
исключение, оно пробрасывается, но следующий span начнёт новую трассу.

## HTTP middleware

```php
use PhpSoftBox\Profiler\Middleware\ProfilerMiddleware;

$app->add(ProfilerMiddleware::class);
```

Middleware создает root trace `http.request`, добавляет `X-Profile-Id` и
`Server-Timing`.

Профайлер не влияет на результат запроса. Если сбор или сохранение трассы упали, ответ отдаётся без
`X-Profile-Id` и `Server-Timing`, а сбой пишется в лог уровня `warning`, если передан logger
(`new ProfilerMiddleware($profiler, $logger)`). Исключение обработчика пробрасывается как есть, сбой профайлера
его не подменяет.

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
компонентные sections: `database`, `container`, `router`, `multi_tenant`.
