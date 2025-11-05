package com.elvin.javaservice.controller;

import io.opentelemetry.api.OpenTelemetry;
import io.opentelemetry.api.trace.Span;
import io.opentelemetry.api.trace.Tracer;
import io.opentelemetry.context.Scope;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class ExternalController {

    private final Tracer tracer;

    public ExternalController(OpenTelemetry openTelemetry) {
        this.tracer = openTelemetry.getTracer("com.elvin.javaservice.ExternalController");
    }

    @GetMapping("/")
    public String home() {
        Span span = tracer.spanBuilder("home-method-span").startSpan();
        try (Scope scope = span.makeCurrent()) {
            span.setAttribute("user.journey", "entered homepage");
            return "Hello from Java Service! Trace ID: " + Span.current().getSpanContext().getTraceId();
        } finally {
            span.end();
        }
    }
}