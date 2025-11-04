<?php

namespace App;

use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\Exporter\Otlp\Exporter;

class OpenTelemetryBootstrap
{
    public static function init(): void
    {
        try {
            $endpoint = getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://jaeger:4318';
            $serviceName = getenv('OTEL_SERVICE_NAME') ?: 'php-backend';

            // Use simple OTLP exporter
            $exporter = new Exporter([
                'endpoint' => $endpoint . '/v1/traces',
                'protocol' => 'http/protobuf',
            ]);

            $spanProcessor = new BatchSpanProcessor($exporter);

            $tracerProvider = (new TracerProviderBuilder())
                ->addSpanProcessor($spanProcessor)
                ->setResource(ResourceInfoFactory::emptyResource()->merge(
                    ResourceInfoFactory::create([
                        'service.name' => $serviceName,
                        'service.version' => '1.0.0',
                    ])
                ))
                ->build();

            Globals::registerInitializer(function () use ($tracerProvider) {
                return $tracerProvider;
            });
        } catch (\Exception $e) {
            // Log error but don't break application
            error_log('OpenTelemetry initialization failed: ' . $e->getMessage());
        }
    }
}

