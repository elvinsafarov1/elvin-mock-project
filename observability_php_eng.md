# PHP OpenTelemetry Integration

## Overview
This document describes how to enable distributed tracing in the PHP service using OpenTelemetry SDK and export traces to Jaeger via OTLP.

---

## 1. Dependencies

Installed via Composer:

```json
"require": {
  "open-telemetry/sdk": "^1.9",
  "open-telemetry/exporter-otlp": "^1.3",
  "symfony/http-client": "^7.3",
  "nyholm/psr7": "^1.8"
}
```

If not yet installed:

```bash
composer require open-telemetry/sdk:^1.9 open-telemetry/exporter-otlp:^1.3 symfony/http-client nyholm/psr7
```

---

## 2. Environment variables

Add the following to your `docker-compose.yml` or `compose.override.yaml` under the PHP service:

```yaml
environment:
  OTEL_SERVICE_NAME: php-service
  OTEL_EXPORTER_OTLP_PROTOCOL: grpc
  OTEL_EXPORTER_OTLP_ENDPOINT: http://jaeger:4317
  OTEL_TRACES_EXPORTER: otlp
  OTEL_METRICS_EXPORTER: none
  OTEL_LOGS_EXPORTER: none
```

Make sure the Jaeger or Tempo service is running and accessible via the same Docker network.

---

## 3. SDK and exporter setup

Example initialization (`tracing.php` or inside your main `index.php` before app logic):

```php
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerProviderInterface;
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

This sets up a global tracer provider with OTLP export via gRPC.

---

## 4. Propagate traceparent header

To link traces between services, read the `traceparent` header from incoming requests and inject it into the context:

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

// Application logic here

$span->end();
```

When sending requests to other services, inject the current trace context:

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

Ensure the PHP container copies dependencies and starts with the correct entrypoint:

```dockerfile
FROM php:8.2-cli
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --no-progress
CMD ["php", "index.php"]
```

---

## 6. Verify in Jaeger

Once the service is running, open the Jaeger UI (usually at `http://localhost:16686`), select `php-service` under *Service*, and check incoming spans.

If traces do not appear:
- Verify Jaeger endpoint (`OTEL_EXPORTER_OTLP_ENDPOINT`)
- Ensure containers share the same Docker network
- Confirm the OTLP port (4317 for gRPC)

---

## 7. Troubleshooting

**Common issues:**

- `Failed to export spans`: Jaeger is unreachable or endpoint is incorrect.
- `Connection reset`: containers are not in the same network.
- `No spans received`: wrong service name (`OTEL_SERVICE_NAME`) or SDK not initialized.

---

**Done.** Tracing for the PHP service is now active and integrated with the observability stack.

