// src/app/telemetry.ts
import { WebTracerProvider } from '@opentelemetry/sdk-trace-web';
import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-http';
import { BatchSpanProcessor } from '@opentelemetry/sdk-trace-base';
import { DocumentLoadInstrumentation } from '@opentelemetry/instrumentation-document-load';
import { FetchInstrumentation } from '@opentelemetry/instrumentation-fetch';
import { XMLHttpRequestInstrumentation } from '@opentelemetry/instrumentation-xml-http-request';
import { ZoneContextManager } from '@opentelemetry/context-zone';
import { Resource } from '@opentelemetry/resources';
import { SemanticResourceAttributes } from '@opentelemetry/semantic-conventions';
import { registerInstrumentations } from '@opentelemetry/instrumentation';

export async function initOpenTelemetry(): Promise<void> {
  const otlpEndpoint =
    (typeof window !== 'undefined' && (window as any).OTEL_EXPORTER_OTLP_ENDPOINT)
      ? (window as any).OTEL_EXPORTER_OTLP_ENDPOINT
      : 'http://tempo:4318/v1/traces';

  const resource = new Resource({
    [SemanticResourceAttributes.SERVICE_NAME]: 'angular-frontend',
    [SemanticResourceAttributes.SERVICE_VERSION]: '1.0.0',
    [SemanticResourceAttributes.DEPLOYMENT_ENVIRONMENT]: 'production',
  });

  const exporter = new OTLPTraceExporter({ url: otlpEndpoint });

  const provider = new WebTracerProvider({ resource });
  provider.addSpanProcessor(
    new BatchSpanProcessor(exporter, {
      maxQueueSize: 100,
      maxExportBatchSize: 10,
      scheduledDelayMillis: 500,
      exportTimeoutMillis: 30000,
    })
  );

  provider.register({
    contextManager: new ZoneContextManager(),
  });

  registerInstrumentations({
    instrumentations: [
      new DocumentLoadInstrumentation(),
      new FetchInstrumentation({
        propagateTraceHeaderCorsUrls: [
          /http:\/\/localhost:8080\/.*/,
          /http:\/\/localhost:8000\/.*/,
          new RegExp(`${window.location.origin}/.*`),
        ],
        clearTimingResources: true,
      }),
      new XMLHttpRequestInstrumentation({
        propagateTraceHeaderCorsUrls: [
          /http:\/\/localhost:8080\/.*/,
          /http:\/\/localhost:8000\/.*/,
          new RegExp(`${window.location.origin}/.*`),
        ],
      }),
    ],
  });

  console.log('✅ OpenTelemetry initialized, exporting to:', otlpEndpoint);
}
