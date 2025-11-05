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
        $serviceName = $_ENV['OTEL_SERVICE_NAME'] ?? 'php-backend';
        $endpoint = $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'] ?? 'http://jaeger:4318';
        
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(
                Attributes::create([
                    'service.name' => $serviceName,
                    'service.version' => '1.0.0',
                    'deployment.environment' => $_ENV['APP_ENV'] ?? 'production',
                ])
            )
        );

        $httpClient = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
        $streamFactory = new HttpFactory();
        $requestFactory = new HttpFactory();

        $exporter = new SpanExporter(
            $httpClient,
            $requestFactory,
            $streamFactory,
            $endpoint . '/v1/traces'
        );

        $spanProcessor = new BatchSpanProcessor(
            $exporter,
            Globals::clockFactory()
        );

        $tracerProvider = new TracerProvider(
            [$spanProcessor],
            null,
            $resource
        );

        // Register as global tracer provider
        Globals::registerInitializer(function () use ($tracerProvider) {
            return $tracerProvider;
        });

        return $tracerProvider;
    }
}