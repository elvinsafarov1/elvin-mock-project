import { Resource } from '@opentelemetry/resources';
import { SemanticResourceAttributes } from '@opentelemetry/semantic-conventions';
import { WebTracerProvider } from '@opentelemetry/sdk-trace-web';
import { BatchSpanProcessor } from '@opentelemetry/sdk-trace-base';
import { OTLPTraceExporter } from '@opentelemetry/exporter-otlp-http';
import { getWebAutoInstrumentations } from '@opentelemetry/auto-instrumentations-web';

export async function initOpenTelemetry(): Promise<void> {
  // Use environment variable or default to Jaeger endpoint
  const endpoint = (window as any).__OTEL_ENDPOINT__ || 'http://localhost:4318';
  const serviceName = (window as any).__OTEL_SERVICE_NAME__ || 'angular-frontend';

  const resource = new Resource({
    [SemanticResourceAttributes.SERVICE_NAME]: serviceName,
    [SemanticResourceAttributes.SERVICE_VERSION]: '1.0.0',
  });

  const provider = new WebTracerProvider({
    resource: resource,
  });

  const exporter = new OTLPTraceExporter({
    url: `${endpoint}/v1/traces`,
  });

  provider.addSpanProcessor(new BatchSpanProcessor(exporter));
  provider.register();

  // Auto-instrumentation for HTTP requests
  getWebAutoInstrumentations({
    '@opentelemetry/instrumentation-fetch': {
      enabled: true,
    },
    '@opentelemetry/instrumentation-xml-http-request': {
      enabled: true,
    },
  });

  console.log('OpenTelemetry initialized for Angular frontend', { endpoint, serviceName });
}

