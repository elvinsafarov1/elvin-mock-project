# Интеграция OpenTelemetry в Angular фронтенд

## Контекст и назначение
Данный документ описывает, как включить распределённый трейсинг на фронтенде (Angular 17) с помощью OpenTelemetry Web SDK и обеспечить отправку трейсов в Tempo или Jaeger через OTLP HTTP. Руководство охватывает два варианта: с использованием `HttpInterceptor` и без него (автоматическая инструментализация).  
Документ используется совместно с руководством `observability_php.md` для обеспечения единой цепочки распределённого трейсинга между фронтендом и backend-сервисами.

---

## 1. Зависимости

Необходимые зависимости уже определены в `package.json`:

```json
"dependencies": {
  "@opentelemetry/api": "^1.7.0",
  "@opentelemetry/context-zone": "^1.19.0",
  "@opentelemetry/exporter-trace-otlp-http": "^0.52.0",
  "@opentelemetry/instrumentation": "^0.48.0",
  "@opentelemetry/instrumentation-document-load": "^0.48.0",
  "@opentelemetry/instrumentation-fetch": "^0.48.0",
  "@opentelemetry/instrumentation-xml-http-request": "^0.48.0",
  "@opentelemetry/resources": "^1.25.1",
  "@opentelemetry/sdk-trace-web": "^1.25.1",
  "@opentelemetry/semantic-conventions": "^1.25.1"
}
```

При необходимости можно установить зависимости вручную:

```bash
npm install @opentelemetry/api @opentelemetry/sdk-trace-web @opentelemetry/exporter-trace-otlp-http @opentelemetry/context-zone @opentelemetry/instrumentation @opentelemetry/instrumentation-fetch @opentelemetry/instrumentation-xml-http-request @opentelemetry/instrumentation-document-load
```

Рекомендуемые версии:
```
Angular 17+
@opentelemetry/sdk-trace-web 1.25.1
Tempo / Jaeger all-in-one 1.54+
```

---

## 2. Структура и размещение файлов

- Файл `telemetry.ts` должен находиться в директории `src/` и импортироваться в `main.ts` **до инициализации `AppModule`**.
- Файл `env.js` (при использовании runtime-настроек) размещается в корне собранного фронтенда (`/usr/share/nginx/html/`), рядом с `index.html`.

---

## 3. Endpoint экспорта трейсов

В файле `telemetry.ts` используется следующая логика определения endpoint:

```ts
const otlpEndpoint =
  (typeof window !== 'undefined' && (window as any).OTEL_EXPORTER_OTLP_ENDPOINT)
    ? (window as any).OTEL_EXPORTER_OTLP_ENDPOINT
    : 'http://tempo:4318/v1/traces';
```

По умолчанию используется Tempo (`http://tempo:4318/v1/traces`).  
При необходимости можно переопределить endpoint без пересборки, добавив в `index.html`:

```html
<script>
  window.OTEL_EXPORTER_OTLP_ENDPOINT = "http://jaeger:4318/v1/traces";
</script>
```

### Пример файла `env.js`

```js
window.OTEL_EXPORTER_OTLP_ENDPOINT = "http://tempo:4318/v1/traces";
```

Файл должен подключаться в `index.html` до основного JS-бандла:

```html
<script src="env.js"></script>
<script src="main.js"></script>
```

---

## 4. Базовая инициализация (без HttpInterceptor)

Файл `src/telemetry.ts`:

```ts
import { WebTracerProvider } from '@opentelemetry/sdk-trace-web';
import { ConsoleSpanExporter, SimpleSpanProcessor } from '@opentelemetry/sdk-trace-base';
import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-http';
import { Resource } from '@opentelemetry/resources';
import { SemanticResourceAttributes } from '@opentelemetry/semantic-conventions';
import { registerInstrumentations } from '@opentelemetry/instrumentation';
import { FetchInstrumentation } from '@opentelemetry/instrumentation-fetch';
import { XMLHttpRequestInstrumentation } from '@opentelemetry/instrumentation-xml-http-request';
import { DocumentLoadInstrumentation } from '@opentelemetry/instrumentation-document-load';

const otlpEndpoint =
  (typeof window !== 'undefined' && (window as any).OTEL_EXPORTER_OTLP_ENDPOINT)
    ? (window as any).OTEL_EXPORTER_OTLP_ENDPOINT
    : 'http://tempo:4318/v1/traces';

const provider = new WebTracerProvider({
  resource: new Resource({
    [SemanticResourceAttributes.SERVICE_NAME]: 'angular-frontend',
  }),
});

const exporter = new OTLPTraceExporter({ url: otlpEndpoint });
provider.addSpanProcessor(new SimpleSpanProcessor(exporter));
provider.addSpanProcessor(new SimpleSpanProcessor(new ConsoleSpanExporter()));
provider.register();

registerInstrumentations({
  instrumentations: [
    new FetchInstrumentation({ propagateTraceHeaderCorsUrls: /.*/ }),
    new XMLHttpRequestInstrumentation({ propagateTraceHeaderCorsUrls: /.*/ }),
    new DocumentLoadInstrumentation(),
  ],
});
```

Этот вариант автоматически собирает трейсы загрузки документа, `fetch` и `XHR`, без необходимости изменять Angular код.

Импорт в `main.ts`:
```ts
import './telemetry';
```

---

## 5. Вариант с HttpInterceptor

Если проект использует `HttpClient`, рекомендуется пробрасывать `traceparent` через Angular `HttpInterceptor`.

```ts
import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent } from '@angular/common/http';
import { Observable } from 'rxjs';
import { context, propagation, trace } from '@opentelemetry/api';

@Injectable()
export class TraceInterceptor implements HttpInterceptor {
  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    const span = trace.getTracer('angular-frontend').startSpan(req.url);
    const ctx = trace.setSpan(context.active(), span);

    const carrier: any = {};
    propagation.inject(ctx, carrier);

    const tracedRequest = req.clone({ setHeaders: carrier });

    return next.handle(tracedRequest).pipe({
      finalize: () => span.end()
    });
  }
}
```

Регистрация интерсептора выполняется в `app.module.ts`:

```ts
import { HTTP_INTERCEPTORS } from '@angular/common/http';
import { TraceInterceptor } from './interceptors/trace.interceptor';

@NgModule({
  providers: [
    { provide: HTTP_INTERCEPTORS, useClass: TraceInterceptor, multi: true }
  ]
})
export class AppModule {}
```

---

## 6. Dockerfile

Пример минимального Dockerfile для сборки и запуска Angular:

```dockerfile
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build -- --configuration production

FROM nginx:alpine
COPY --from=build /app/dist/angular-frontend /usr/share/nginx/html
COPY env.js /usr/share/nginx/html/
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

---

## 7. docker-compose.yml пример

```yaml
version: '3.8'
services:
  frontend:
    build: ./frontend
    ports:
      - "8080:80"
    environment:
      - OTEL_EXPORTER_OTLP_ENDPOINT=http://tempo:4318/v1/traces
    networks:
      - observability

  tempo:
    image: grafana/tempo:2.4.1
    ports:
      - "16686:16686" # Jaeger UI совместимый интерфейс
      - "4318:4318"   # OTLP HTTP

networks:
  observability:
    driver: bridge
```

---

## 8. Проверка и быстрая диагностика

После выполнения `docker compose up`:

1. Открываем фронтенд по адресу `http://localhost:8080`
2. Выполняем несколько переходов и сетевых запросов.
3. Проверяем наличие трейсов в интерфейсе Tempo или Jaeger:
   - Tempo: `http://localhost:16686/search`
   - Jaeger (all-in-one): `http://localhost:16686`

Для быстрой проверки доступности и логов можно использовать:
```bash
curl -I http://localhost:8080

docker compose logs tempo | grep span
```

В списке сервисов должен отображаться `angular-frontend`.

---

## 9. Troubleshooting

| Симптом | Возможная причина | Решение |
|----------|-------------------|----------|
| `CORS error on traces POST` | Tempo или Jaeger не разрешают кросс-доменные запросы | Добавить `Access-Control-Allow-Origin: *` в collector или использовать прокси | 
| `No spans received` | SDK не инициализирован | Проверить импорт `telemetry.ts` в `main.ts` |
| `traceparent missing` | HttpInterceptor не подключён | Проверить регистрацию в `app.module.ts` |

---

**Готово.** После выполнения всех шагов фронтенд-сервис будет отправлять трейсы в Tempo или Jaeger и участвовать в распределённой трассировке совместно с backend-компонентами.

