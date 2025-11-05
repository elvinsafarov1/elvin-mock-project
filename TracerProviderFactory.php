<?php

namespace App\OpenTelemetry;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class TracerProviderFactory
{
    public static function create(): TracerProvider
    {
        // Create resource with service information
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(
                Attributes::create([
                    'service.name' => 'php-backend',
                    'service.version' => '1.0.0',
                    'deployment.environment' => $_ENV['APP_ENV'] ?? 'production',
                ])
            )
        );

        // Create HTTP client for OTLP exporter
        $httpClient = new Client([
            'timeout' => 10,
        ]);

        $streamFactory = new HttpFactory();
        $requestFactory = new HttpFactory();

        // Configure OTLP HTTP exporter
        $exporter = new SpanExporter(
            $httpClient,
            $requestFactory,
            $streamFactory,
            'http://jaeger:4318/v1/traces'
        );

        // Create batch span processor
        $spanProcessor = new BatchSpanProcessor(
            $exporter,
            Globals::clockFactory()
        );

        // Create and return tracer provider
        $tracerProvider = new TracerProvider(
            [$spanProcessor],
            null,
            $resource
        );

        return $tracerProvider;
    }
}
