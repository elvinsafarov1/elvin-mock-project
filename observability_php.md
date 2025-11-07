# Интеграция OpenTelemetry в PHP

## Контекст и назначение

Этот документ описывает, как подключить OpenTelemetry к PHP‑сервису для экспорта трейсов в Jaeger через OTLP. Гайд применим ко всем PHP‑микросервисам, использующим общую observability‑инфраструктуру.

---

## 1. Зависимости

Установлены через Composer:

```json
"require": {
  "open-telemetry/sdk": "^1.9",
  "open-telemetry/exporter-otlp": "^1.3",
  "symfony/http-client": "^7.3",
  "nyholm/psr7": "^1.8"
}
```

Если пакеты ещё не добавлены:

```bash
composer require open-telemetry/sdk:^1.9 open-telemetry/exporter-otlp:^1.3 symfony/http-client nyholm/psr7
```

Рекомендуемые версии:

```
PHP >= 8.1
open-telemetry/sdk 1.9
jaegertracing/all-in-one 1.54
```

---

## 2. Переменные окружения

Надо добавить следующие переменные в секцию PHP‑сервиса в `docker-compose.yml` или `compose.override.yaml`:

```yaml
environment:
  OTEL_SERVICE_NAME: php-service
  OTEL_EXPORTER_OTLP_PROTOCOL: grpc
  OTEL_EXPORTER_OTLP_ENDPOINT: http://jaeger:4317
  OTEL_TRACES_EXPORTER: otlp
  OTEL_METRICS_EXPORTER: none
  OTEL_LOGS_EXPORTER: none
```

Надо убедиться, что сервис Jaeger запущен и находится в одной Docker‑сети с PHP‑контейнером.

---

## 3. Настройка SDK и экспортера

Инициализацию помещаем в отдельный файл `tracing.php` или в начало `index.php` до загрузки основной логики приложения:

```php
use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\Exporter\OtlpGrpcExporter;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SDK\Common\Attribute\Attributes;

require __DIR__ . '/vendor/autoload.php';

$exporter = new OtlpGrpcExporter();
$resource = ResourceInfo::create(Attributes::create([
    ResourceAttributes::SERVICE_NAME => getenv('OTEL_SERVICE_NAME') ?: 'php-service',
]));

$tracerProvider = new TracerProvider(
    new SimpleSpanProcessor($exporter),
    $resource
);

Globals::registerTracerProvider($tracerProvider);
```

Эта конфигурация создаёт глобальный провайдер трейсера и экспортирует данные через OTLP (gRPC) в Jaeger.

---

## 4. Проброс traceparent‑заголовка

Чтобы связать трейсы между сервисами, нужно считывать `traceparent` из входящих запросов и передавать контекст дальше.

### Извлечение контекста из входящего запроса

```php
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use Nyholm\Psr7\ServerRequest;

$request = ServerRequest::fromGlobals();
$propagator = TraceContextPropagator::getInstance();
$context = $propagator->extract($request->getHeaders());

$tracer = Globals::tracerProvider()->getTracer('php-service');

$span = $tracer->spanBuilder('incoming_request')
    ->setParent($context)
    ->startSpan();

// Основная логика приложения

$span->end();
```

### Проброс контекста при исходящем запросе

```php
use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
$propagator = TraceContextPropagator::getInstance();
$headers = [];
$propagator->inject($headers);

$response = $client->request('GET', 'http://java-service/api/test', [
    'headers' => $headers,
]);
```

---

## 5. Dockerfile

Надо убедиться, что контейнер PHP устанавливает зависимости и запускается корректно:

```dockerfile
FROM php:8.2-cli
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --no-progress
CMD ["php", "index.php"]
```

---

## 6. Пример docker-compose для Jaeger

```yaml
jaeger:
  image: jaegertracing/all-in-one:1.54
  ports:
    - "16686:16686"   # Web UI
    - "4317:4317"     # gRPC OTLP
    - "4318:4318"     # HTTP OTLP
```

Jaeger будет доступен по адресу: [http://localhost:16686](http://localhost:16686)

---

## 7. Проверка и быстрый тест

После запуска сервисов выполняем:

```bash
docker compose logs php-service | grep otel
```

Если экспорт успешен — появятся записи о передаче трейсов.

Далее открываем Jaeger UI → `http://localhost:16686/search?service=php-service` и проверь спаны.

Если трассы не отображаются:

* Проверь `OTEL_EXPORTER_OTLP_ENDPOINT`
* Убедись, что контейнеры в одной сети
* Проверь порт 4317 (gRPC)

---

## 8. Troubleshooting

**Типичные ошибки:**

| Симптом                  | Возможная причина                               | Решение                                |
| ------------------------ | ----------------------------------------------- | -------------------------------------- |
| `Failed to export spans` | Jaeger недоступен или неправильный endpoint     | Проверь `OTEL_EXPORTER_OTLP_ENDPOINT`  |
| `Connection reset`       | Контейнеры не в одной сети                      | Проверь `networks` в docker-compose    |
| `No spans received`      | SDK не инициализирован или неверное имя сервиса | Проверь `OTEL_SERVICE_NAME` и init-код |
| Видно только PHP‑трейсы  | Другие сервисы не пробрасывают `traceparent`    | Добавь header propagation              |

---

**Готово.** Трейсинг для PHP‑сервиса активен и интегрирован в общую observability‑инфраструктуру.
