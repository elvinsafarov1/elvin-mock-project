<?php

declare(strict_types=1);

namespace App;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryBootstrap
{
    public static function init(): void
    {
        // Define the service name resource attribute
        $resource = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => 'php-backend', // Hard-code the service name
            ]))
        );

        // Manually create the OTLP transport with the Jaeger endpoint hard-coded
        $transport = (new OtlpHttpTransportFactory())->create(
             'http://jaeger:4318/v1/traces', // <-- THE GUARANTEED FIX: Hard-coded correct endpoint
            'application/x-protobuf'
        );

        $exporter = new SpanExporter($transport);

        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor($exporter),
            new AlwaysOnSampler(),
            $resource
        );

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->buildAndRegisterGlobal();
    }
}