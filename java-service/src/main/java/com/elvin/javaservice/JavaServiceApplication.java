package com.elvin.javaservice;

import io.opentelemetry.api.OpenTelemetry;
import io.opentelemetry.exporter.otlp.trace.OtlpGrpcSpanExporter;
import io.opentelemetry.sdk.OpenTelemetrySdk;
import io.opentelemetry.sdk.resources.Resource;
import io.opentelemetry.sdk.trace.SdkTracerProvider;
import io.opentelemetry.sdk.trace.export.BatchSpanProcessor;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.Bean;

@SpringBootApplication
public class JavaServiceApplication {

    @Bean
    public OpenTelemetry openTelemetry() {
        // Create resource with service name
        Resource resource = Resource.getDefault().toBuilder()
                .put("service.name", "java-service")
                .put("service.version", "1.0.0")
                .build();

        // Configure the OTLP gRPC exporter with correct port
        OtlpGrpcSpanExporter spanExporter = OtlpGrpcSpanExporter.builder()
                .setEndpoint("http://jaeger:4317")  // ✅ FIXED: Use port 4317 for gRPC
                .build();

        // Create tracer provider with batch span processor
        SdkTracerProvider sdkTracerProvider = SdkTracerProvider.builder()
                .addSpanProcessor(
                        BatchSpanProcessor.builder(spanExporter)
                                .build()
                )
                .setResource(resource)
                .build();

        // Build and register OpenTelemetry SDK
        OpenTelemetrySdk openTelemetry = OpenTelemetrySdk.builder()
                .setTracerProvider(sdkTracerProvider)
                .buildAndRegisterGlobal();

        // Shutdown hook to ensure spans are flushed
        Runtime.getRuntime().addShutdownHook(new Thread(sdkTracerProvider::close));

        return openTelemetry;
    }

    public static void main(String[] args) {
        SpringApplication.run(JavaServiceApplication.class, args);
    }
}
